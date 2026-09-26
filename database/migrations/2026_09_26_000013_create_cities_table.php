<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master kabupaten/kota (B12) untuk pemilih kota.
 *
 * `(name)` berindeks untuk pencarian awalan; `(province)` untuk pengelompokan.
 * Data acuan — diisi `CitySeeder`, ikut ke berkas pemasangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('province', 80);
            $table->string('type', 12);
            $table->unsignedInteger('sort_order')->default(0);

            $table->index('name');
            $table->index('province');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
