<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Thumbnail video chat. Server hosting bersama tidak punya ffmpeg, jadi
 * bingkai awal diambil PERANGKAT dan diunggah bersama videonya. Lampiran
 * lama (dan non-video) tetap null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_attachments', function (Blueprint $table): void {
            $table->string('thumbnail_path')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('chat_attachments', function (Blueprint $table): void {
            $table->dropColumn('thumbnail_path');
        });
    }
};
