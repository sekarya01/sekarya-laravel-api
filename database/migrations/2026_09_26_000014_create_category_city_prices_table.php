<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga referensi per (kategori, kota) — U17.
 *
 * Baris hanya ada bila sampel kotanya cukup (`category_prices.city_min_sample`).
 * Bila tidak, `GET categories?city=` jatuh ke angka nasional di `categories`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_city_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('city', 80);
            $table->unsignedBigInteger('ref_price_min')->nullable();
            $table->unsignedBigInteger('ref_price_max')->nullable();
            $table->unsignedBigInteger('ref_price_median')->nullable();
            $table->unsignedInteger('ref_sample_size')->default(0);
            $table->timestamp('ref_computed_at')->nullable();

            $table->unique(['category_id', 'city'], 'category_city_prices_category_city_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_city_prices');
    }
};
