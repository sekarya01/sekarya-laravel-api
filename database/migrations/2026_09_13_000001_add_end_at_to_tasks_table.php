<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            // Jadwal selesai opsional dari aplikasi (langkah 3 pasang tugas).
            // Nullable: task lama dan task tanpa estimasi selesai tetap sah.
            $table->timestamp('end_at')->nullable()->after('needed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('end_at');
        });
    }
};
