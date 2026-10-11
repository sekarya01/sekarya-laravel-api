<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit API vs Sekarya Mobile (2026-10-11):
 *
 * - `task_bookmarks`: mobile menyimpan bookmark di perangkat (SQLite lokal) dan
 *   mengabaikan endpoint server-nya, jadi endpoint `tasks/bookmarked` +
 *   `PUT/DELETE tasks/{task}/bookmark` dan field `is_bookmarked` dibuang.
 * - `job_batches`: bawaan template Laravel; tidak ada `Bus::batch` di aplikasi.
 *
 * DESTRUKTIF: isi `task_bookmarks` hilang. `down()` membuat ulang kedua tabel
 * kosong dengan struktur aslinya (isi lama tidak bisa dikembalikan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('task_bookmarks');
        Schema::dropIfExists('job_batches');
    }

    public function down(): void
    {
        Schema::create('job_batches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('task_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'task_id'], 'task_bookmarks_user_task_unique');
            $table->index(['user_id', 'created_at', 'id'], 'task_bookmarks_user_latest_index');
        });
    }
};
