<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lokasi langsung + checklist pekerja (B8, B10).
 *
 * `live_*` diisi pekerja yang sedang `on_the_way`; presisi 7 desimal menyamai
 * `tasks`. `checklist_state` adalah larik boolean yang sejajar dengan
 * `tasks.checklist`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->decimal('live_latitude', 10, 7)->nullable()->after('rejected_at');
            $table->decimal('live_longitude', 10, 7)->nullable()->after('live_latitude');
            $table->timestamp('live_updated_at')->nullable()->after('live_longitude');
            $table->json('checklist_state')->nullable()->after('live_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropColumn(['live_latitude', 'live_longitude', 'live_updated_at', 'checklist_state']);
        });
    }
};
