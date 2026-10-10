<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kotak masuk notifikasi dipindah ke PERANGKAT (keputusan produk 2026-10-10):
 * server cukup mengirim push, riwayat lonceng disimpan aplikasi (Room).
 * Menyimpan salinan tiap push di server hanya menghabiskan sumber daya.
 *
 * DESTRUKTIF: seluruh isi `user_notifications` hilang. `down()` membuat ulang
 * tabel kosong dengan struktur aslinya (isi lama tidak bisa dikembalikan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_notifications');
    }

    public function down(): void
    {
        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title', 255);
            $table->string('body', 500);
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at', 'id']);
            $table->index(['user_id', 'read_at', 'created_at']);
        });
    }
};
