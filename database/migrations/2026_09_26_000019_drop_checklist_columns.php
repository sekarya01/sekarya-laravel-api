<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus fitur checklist pekerjaan ("Persiapan Mitra", B10).
 *
 * Kolom `tasks.checklist` + `activities.checklist_state` beserta endpoint
 * `PUT activities/{activity}/checklist` dibuang (keputusan user 2026-09-26).
 * `live_*` di activities (B8, migrasi 000010) TIDAK ikut — tetap dipakai.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', static function (Blueprint $table): void {
            $table->dropColumn('checklist');
        });
        Schema::table('activities', static function (Blueprint $table): void {
            $table->dropColumn('checklist_state');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', static function (Blueprint $table): void {
            $table->json('checklist')->nullable();
        });
        Schema::table('activities', static function (Blueprint $table): void {
            $table->json('checklist_state')->nullable();
        });
    }
};
