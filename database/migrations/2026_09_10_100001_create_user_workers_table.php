<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil PEKERJA, dipisah dari akun.
 *
 * Satu orang di aplikasi ini bisa jadi dua-duanya — hari ini memberi kerja,
 * besok mencari kerja (`users.active_mode`). Yang dipisah di sini bukan
 * orangnya, melainkan sisi pekerjanya: alamat tempat ia mau menerima
 * pekerjaan, nomor yang boleh dihubungi pemberi kerja, foto yang ia tampilkan
 * sebagai pekerja, dan seluruh reputasinya sebagai pekerja.
 *
 * TIGA hal yang menentukan bentuk tabel ini:
 *
 * 1. **Kolom identitas di sini adalah PELENGKAP, bukan salinan.** `display_name`,
 *    `contact_phone`, `avatar_path` dan alamat semuanya NULLABLE, dan NULL
 *    berarti "pakai punya akun" — bukan "kosong". Resolusinya di
 *    `App\Models\UserWorker`, satu tempat, dipakai semua Resource. Kalau
 *    kolom-kolom ini wajib diisi, dua tabel akan menyimpan jawaban atas
 *    pertanyaan yang sama dan keduanya bisa benar sendiri-sendiri: ganti nama
 *    di profil akun tidak akan terlihat di profil pekerja, tanpa galat apa pun.
 *
 * 2. **Jenis kelamin dan tanggal lahir TIDAK ada di sini.** Keduanya identitas
 *    orangnya, bukan peran yang sedang ia jalankan — satu orang tidak berganti
 *    tanggal lahir saat berpindah mode. Sumbernya `users`, dan API tetap
 *    mengeluarkannya di profil pekerja (`gender`, `age`) lewat relasi.
 *
 * 3. **Agregat reputasi PINDAH ke sini**, tidak diduplikasi — migrasi
 *    berikutnya yang memindahkan datanya lalu menghapus kolomnya dari `users`.
 *    Reputasi sebagai pekerja adalah milik profil pekerja; yang tertinggal di
 *    `users` hanya reputasi sebagai pemberi kerja.
 *
 * Tidak ada kolom `ulid`: profil ini tidak pernah muncul di URL. Ia selalu
 * dialamatkan lewat pemiliknya (`/me/worker`), jadi ULID di sini hanya kolom
 * unik yang tidak pernah dibaca siapa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_workers', function (Blueprint $table): void {
            $table->id();

            // unique + cascade: satu profil pekerja per orang, dijamin basis
            // data. Baris yatim tidak mungkin ada — kalau akunnya benar-benar
            // dihapus, profilnya ikut.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // ── Pelengkap identitas. NULL = pakai nilai dari `users`. ───────
            $table->string('display_name')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('province', 80)->nullable();
            $table->string('postal_code', 10)->nullable();

            // ── Lokasi kerja: dari mana ia bersedia berangkat, dan sejauh apa.
            // Presisi 7 desimal menyamai `tasks` supaya perbandingan jarak
            // memakai skala yang sama.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('radius_km')->nullable();

            // ── Reputasi sebagai pekerja. Dipindah dari `users` oleh migrasi
            // berikutnya. Ditulis Action, TIDAK pernah mass-assignable.
            $table->decimal('worker_rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('worker_rating_count')->default(0);
            $table->unsignedInteger('tasks_completed')->default(0);
            $table->unsignedInteger('bids_won')->default(0);

            $table->timestamps();

            // Pengurutan penawaran menurut reputasi (ListBidsAction sort=rating).
            $table->index(['worker_rating_avg', 'tasks_completed']);
            // Bounding box dulu, haversine belakangan — sama seperti `tasks`.
            $table->index(['latitude', 'longitude']);
            $table->index(['city', 'province']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_workers');
    }
};
