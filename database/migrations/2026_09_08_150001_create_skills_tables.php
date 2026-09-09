<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skills dinormalisasi, bukan kolom JSON.
 *
 * Alasannya performa: JSON di SQLite tidak bisa diindeks, jadi
 * `WHERE skills LIKE '%setrika%'` atau `json_each` akan memindai seluruh tabel.
 * Pivot dengan index membuat filter skills jadi lookup, bukan scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index(['category_id', 'is_active']);
        });

        // Keahlian yang DIBUTUHKAN sebuah task.
        // Nama mengikuti konvensi pivot Laravel (alfabetis) supaya relasi
        // belongsToMany tidak perlu menyebut nama tabel secara manual.
        Schema::create('skill_task', function (Blueprint $table): void {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();

            $table->primary(['task_id', 'skill_id']);
            // Arah yang dipakai feed: "task mana yang butuh skill ini".
            $table->index(['skill_id', 'task_id']);
        });

        // Keahlian yang DIMILIKI seseorang.
        Schema::create('skill_user', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();

            $table->primary(['user_id', 'skill_id']);
            $table->index(['skill_id', 'user_id']);
        });

        // Kolom JSON diganti tabel di atas — satu sumber kebenaran.
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('skills');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('skills')->nullable()->after('bio');
        });

        Schema::dropIfExists('skill_user');
        Schema::dropIfExists('skill_task');
        Schema::dropIfExists('skills');
    }
};
