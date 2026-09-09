<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('task_number', 20)->unique();
            $table->foreignId('poster_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained();

            $table->string('title', 180);
            $table->text('description');
            // Opsi tambahan / request. JSON karena bentuknya masih bebas —
            // promosikan jadi kolom begitu perlu difilter.
            $table->json('options')->nullable();
            $table->json('photos')->nullable();

            // budget_max SENGAJA nullable: max opsional.
            $table->unsignedBigInteger('budget_min');
            $table->unsignedBigInteger('budget_max')->nullable();
            // Snapshot harga referensi kategori saat task dibuat.
            $table->unsignedBigInteger('ref_price_median')->nullable();

            $table->string('location_text')->nullable();
            $table->string('city', 80)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_remote')->default(false);

            $table->timestamp('needed_at')->nullable();
            $table->timestamp('bidding_closes_at')->nullable();

            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('bids_count')->default(0);
            $table->unsignedBigInteger('accepted_bid_id')->nullable()->unique();
            $table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('agreed_amount')->nullable();

            $table->timestamp('dealt_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 12)->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['category_id', 'status', 'created_at']);
            $table->index(['poster_id', 'status', 'created_at']);
            $table->index(['worker_id', 'status', 'created_at']);
            $table->index(['city', 'status', 'created_at']);
            $table->index(['latitude', 'longitude']);
            $table->index(['status', 'bidding_closes_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
