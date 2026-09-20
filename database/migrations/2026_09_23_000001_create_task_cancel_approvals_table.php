<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suara tiap pekerja atas satu permintaan pembatalan.
 *
 * Sebelumnya satu jawaban sudah menutup permintaan: pekerja pertama yang
 * menekan "Setuju" membatalkan pekerjaan semua orang, termasuk yang belum
 * ditanya. Aturannya sekarang SEMUA pekerja harus setuju, dan itu menuntut
 * satu baris per orang — jumlah "sudah setuju" tidak bisa disimpulkan dari
 * satu kolom `decided_by` di permintaannya.
 *
 * Barisnya dibuat SEKALIGUS saat permintaan dibuat, dalam keadaan `pending`.
 * Dengan begitu daftar penjawab TERKUNCI di titik itu: pekerja yang diterima
 * sesudahnya tidak ikut menentukan, dan "semua sudah setuju" punya pembagi
 * yang tetap. Tanpa itu, seorang pekerja baru bisa membatalkan kebulatan yang
 * sudah tercapai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_cancel_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cancel_request_id')
                ->constrained('task_cancel_requests')
                ->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('users')->cascadeOnDelete();

            // pending | approved | rejected
            $table->string('status', 16)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // Satu suara per pekerja per permintaan.
            $table->unique(['cancel_request_id', 'worker_id']);
            // "Ada yang masih menunggu?" — pertanyaan yang ditanyakan tiap
            // kali satu suara masuk.
            $table->index(['cancel_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_cancel_approvals');
    }
};
