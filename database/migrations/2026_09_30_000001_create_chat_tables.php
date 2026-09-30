<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat per task — satu room per task, lahir saat pekerjaan dibuka (DEAL).
 *
 * - `chat_rooms.task_id` UNIQUE: satu task satu room. Nama room = judul task
 *   (dibaca langsung dari `tasks`, tidak disalin — ganti judul ikut terlihat).
 * - Room `deactivated` di-soft-delete dan pesan/lampirannya DIHAPUS PERMANEN;
 *   barisnya sendiri disisakan supaya klien mendapat 410, bukan 404.
 * - `chat_messages.id` ULID: urut waktu, jadi penanda baca/terima peserta
 *   cukup SATU id (`last_read_message_id`) — pesan dengan id ≤ penanda itu
 *   sudah dibaca. `(room_id, id)` melayani hitungan belum-dibaca.
 * - `(room_id, sender_id, client_message_id)` UNIQUE = idempotensi kirim
 *   ulang dari klien. Pesan `system` (sender NULL, client id NULL) tidak
 *   terkena — MySQL mengizinkan NULL berulang.
 * - `chat_attachments` lahir dari unggahan, sebelum pesannya ada; satu
 *   lampiran paling banyak satu pesan (`attachment_id` UNIQUE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('task_id')->unique()->constrained()->cascadeOnDelete();
            // `individual` (10) / `group` (5).
            $table->string('type', 12);
            // `active` / `expired` / `deactivated` (11).
            $table->string('status', 12)->default('active');
            $table->char('last_message_id', 26)->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Daftar room diurut aktivitas terakhir; pembersihan membaca
            // room `expired` yang sudah lewat masa simpan.
            $table->index(['updated_at', 'id']);
            $table->index(['status', 'expired_at']);
        });

        Schema::create('chat_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // `user` (pemberi kerja) / `worker`.
            $table->string('type', 8);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->char('last_delivered_message_id', 26)->nullable();
            $table->char('last_read_message_id', 26)->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('muted_at')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
            $table->index(['user_id', 'room_id']);
        });

        Schema::create('chat_attachments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->cascadeOnDelete();
            // `image` / `video` / `audio` / `file`.
            $table->string('kind', 8);
            $table->string('path', 255);
            // Nama asli dari perangkat — hanya DITAMPILKAN (dokumen), tidak
            // pernah menjadi nama berkas di disk.
            $table->string('file_name', 255);
            $table->string('extension', 10);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('duration')->default(0);
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->json('waveform')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['uploader_id', 'created_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_message_id', 64)->nullable();
            // text/reply/image/video/audio/file/system.
            $table->string('type', 8);
            $table->string('reply_type', 8)->nullable();
            $table->char('replied_message_id', 26)->nullable();
            $table->char('attachment_id', 26)->nullable()->unique();
            $table->foreign('attachment_id')->references('id')->on('chat_attachments')->nullOnDelete();
            $table->text('caption')->nullable();
            $table->string('system_event', 32)->nullable();
            $table->json('system_params')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['room_id', 'sender_id', 'client_message_id']);
            $table->index(['room_id', 'created_at', 'id']);
            $table->index(['room_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_attachments');
        Schema::dropIfExists('chat_participants');
        Schema::dropIfExists('chat_rooms');
    }
};
