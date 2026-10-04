<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Avatar room DIBEKUKAN saat room lahir: path foto pertama task pada saat
 * deal, atau null bila task belum berfoto. Foto yang ditambah/diubah
 * sesudahnya tidak lagi mengubah avatar room. Room lama tidak diisi — null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table): void {
            $table->string('avatar')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table): void {
            $table->dropColumn('avatar');
        });
    }
};
