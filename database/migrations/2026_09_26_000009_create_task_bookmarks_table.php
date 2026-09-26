<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookmark/simpan tugas (B11).
 *
 * `unique(user_id, task_id)` membuat "simpan" idempoten di tingkat basis data;
 * `(user_id, created_at, id)` melayani `GET tasks/bookmarked` bercursor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'task_id'], 'task_bookmarks_user_task_unique');
            $table->index(['user_id', 'created_at', 'id'], 'task_bookmarks_user_latest_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_bookmarks');
    }
};
