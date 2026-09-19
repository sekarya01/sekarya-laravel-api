<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pergerakan dana tugas yang ditahan dari SALDO pemberi kerja.
 *
 * Dana tugas dipotong dari saldo saat tugas dipasang (pekerja diminta × harga
 * per orang), disesuaikan saat tugas diubah atau penawaran di atas harga
 * diterima, dan sisanya dikembalikan saat perekrutan ditutup atau tugas batal.
 *
 * Satu tugas bisa bergerak BEBERAPA kali dengan jenis mutasi yang sama. Buku
 * besar dompet mengunci unik (reference_type, reference_id, type), jadi setiap
 * mutasi dompet merujuk barisnya sendiri di tabel ini — bukan ke tagihannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_fund_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            // hold | release | refund
            $table->string('kind', 16);
            $table->unsignedBigInteger('amount');
            $table->string('reason', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_fund_movements');
    }
};
