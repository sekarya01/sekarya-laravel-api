<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->string('description')->nullable();
            $table->string('icon', 60)->nullable();

            // Harga referensi — dihitung dari agreed_amount task yang SELESAI.
            $table->unsignedBigInteger('ref_price_min')->nullable();
            $table->unsignedBigInteger('ref_price_max')->nullable();
            $table->unsignedBigInteger('ref_price_median')->nullable();
            // 0 = masih nilai seed manual, bukan data nyata. UI wajib membedakan.
            $table->unsignedInteger('ref_sample_size')->default(0);
            $table->timestamp('ref_computed_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
