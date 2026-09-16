<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo pengguna: satu dompet per orang, dan buku besar yang menjelaskannya.
 *
 * DUA tabel, bukan satu kolom `users.balance`. Sebuah kolom saldo menjawab
 * "berapa" tanpa bisa menjawab "kenapa" — dan pada uang, pertanyaan kedua
 * itulah yang ditanyakan orang saat angkanya tidak sesuai harapan mereka.
 * Tanpa buku besar, selisih satu rupiah tidak bisa ditelusuri ke kejadian mana
 * pun, dan tidak ada cara membuktikan saldonya benar.
 *
 * `wallets.balance` tetap ada sebagai agregat yang DI-CACHE, bukan sebagai
 * sumber kebenaran: menjumlahkan seluruh buku besar di setiap pembacaan
 * tumbuh sebanding riwayat orangnya. Yang menjaga keduanya tidak melenceng:
 * hanya `App\Support\WalletLedger` yang boleh menyentuh saldo, ia selalu
 * mengunci baris dompet lebih dulu, dan `wallet_entries.balance_after`
 * merekam saldo hasil tiap baris sehingga selisih bisa dilacak ke satu baris
 * tepat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();

            // unique: satu orang satu dompet. Dua baris untuk satu orang
            // berarti saldo yang benar tergantung dompet mana yang kebetulan
            // terbaca lebih dulu.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Agregat yang di-cache. TAK BERTANDA: saldo negatif di aplikasi
            // ini bukan keadaan yang sah, dan kolom bertanda akan menerimanya
            // diam-diam kalau suatu hari ada jalur tulis yang lupa memeriksa.
            $table->unsignedBigInteger('balance')->default(0);

            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('wallet_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();

            $table->string('type', 24);
            $table->string('direction', 6);
            $table->unsignedBigInteger('amount');

            // Saldo SESUDAH baris ini. Tanpa kolom ini, menelusuri selisih
            // berarti menjumlahkan ulang seluruh riwayat dan menebak di mana
            // ia mulai menyimpang.
            $table->unsignedBigInteger('balance_after');

            // Kejadian yang menyebabkannya: wallet_topup, wallet_withdrawal,
            // payment, activity. Pasangan tipe+id tanpa foreign key —
            // tabelnya berbeda-beda, persis seperti task_status_logs.
            $table->string('reference_type', 32)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->string('description', 200)->nullable();

            // HANYA created_at: buku besar append-only. Sebuah baris yang bisa
            // disunting bukan bukti apa pun, dan `updated_at` adalah undangan
            // untuk menyuntingnya.
            $table->timestamp('created_at')->useCurrent();

            // Idempotensi DIJAMIN BASIS DATA, bukan disiplin kode.
            //
            // Satu kejadian hanya boleh menghasilkan satu baris dari jenis
            // yang sama: konfirmasi topup yang terpanggil dua kali, atau
            // persetujuan activity terakhir yang dijalankan dua permintaan
            // bersamaan, akan ditolak #1062 alih-alih menggandakan uang.
            // `type` ikut serta karena satu penarikan sah punya DUA baris —
            // tahanannya dan pengembaliannya.
            //
            // Baris tanpa referensi (koreksi manual) tidak terkena: MySQL
            // mengizinkan NULL berulang di indeks unique.
            $table->unique(['reference_type', 'reference_id', 'type'], 'wallet_entries_reference_unique');

            // Urutan riwayat per dompet — `id` ikut sebagai pemecah seri,
            // syarat cursor pagination.
            $table->index(['wallet_id', 'created_at', 'id']);
            $table->index(['wallet_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_entries');
        Schema::dropIfExists('wallets');
    }
};
