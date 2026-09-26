<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kotak masuk notifikasi in-app (lonceng + badge) — B3.
 *
 * Satu baris per push. Barisnya ditulis oleh jalur yang SAMA dengan push
 * (`App\Support\Push\PushDispatcher`), sebelum job FCM diantrekan: push yang
 * gagal, atau FCM yang dimatikan, tetap meninggalkan barisnya di sini.
 *
 * - `id` ULID, bukan bigint + kolom `ulid`: baris ini hanya pernah dikenali
 *   lewat id publiknya (`POST me/notifications/{id}/read`), dan ULID urut
 *   waktu sehingga tetap menjadi pemutus seri cursor yang benar.
 * - `data` JSON hanya DITAMPILKAN (deep-link), tidak pernah disaring.
 * - `(user_id, created_at, id)` melayani daftar bercursor;
 *   `(user_id, read_at, created_at)` melayani `unread=1` dan
 *   `unread-count` — `read_at IS NULL` adalah kesetaraan di indeks.
 * - Tanpa `updated_at`: satu-satunya perubahan adalah `read_at`, dan kolom itu
 *   sendiri yang mencatat kapan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Sama dengan `data.type` push (PushType). Lebar 40 memberi ruang
            // untuk nilai terpanjang sekarang (`cancel_request_resolved`, 23).
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

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
