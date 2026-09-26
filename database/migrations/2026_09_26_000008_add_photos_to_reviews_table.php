<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto ulasan ("Dengan Foto (12)") — B15.
 *
 * JSON larik path, sama pola dengan `reviews.tags`: hanya DITAMPILKAN, tidak
 * pernah disaring per elemen. Filter "punya foto" memakai `JSON_LENGTH`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->json('photos')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropColumn('photos');
        });
    }
};
