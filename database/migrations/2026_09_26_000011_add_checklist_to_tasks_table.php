<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist pekerjaan (B10): larik langkah yang dicentang pekerja.
 *
 * Di `tasks` karena ia bagian dari definisi pekerjaan, dibuat pemberi kerja
 * (atau template kategori). `activities.checklist_state` menyimpan centangnya
 * per pekerja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->json('checklist')->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('checklist');
        });
    }
};
