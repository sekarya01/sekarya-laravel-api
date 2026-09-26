<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks untuk filter jadwal feed (U14).
 *
 * `(status, needed_at)` melayani `GET tasks?needed_from&needed_to`: feed selalu
 * menyaring `status`, lalu rentang `needed_at` — kesetaraan di depan, rentang
 * di belakang, jadi tidak ada filesort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->index(['status', 'needed_at'], 'tasks_status_needed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_status_needed_at_index');
        });
    }
};
