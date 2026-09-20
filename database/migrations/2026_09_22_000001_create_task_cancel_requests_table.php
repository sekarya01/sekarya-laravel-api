<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan pembatalan yang butuh persetujuan pekerja.
 *
 * `POST tasks/{task}/cancel` membatalkan LANGSUNG — itu benar hanya selama
 * belum ada pekerja yang menerima (belum deal). Begitu ada yang deal,
 * pembatalan sepihak memutus orang yang sudah mengosongkan jadwalnya, jadi
 * ia lewat sini: poster MEMINTA, pekerja MENYETUJUI atau MENOLAK.
 *
 * Satu task hanya boleh punya SATU permintaan `pending` dalam satu waktu
 * (unique index parsial tidak portabel di MySQL untuk status, jadi
 * keunikan dijaga di Action, bukan di skema).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_cancel_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();

            $table->string('reason', 255)->nullable();
            $table->string('status', 24)->default('pending');

            $table->foreignId('decided_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();

            $table->timestamps();

            // Antrean per task: permintaan pending-nya di depan.
            $table->index(['task_id', 'status', 'id']);
            // Riwayat milik peminta.
            $table->index(['requested_by', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_cancel_requests');
    }
};
