<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_id')->constrained('users')->cascadeOnDelete();

            // Budget dari penerima kerja sendiri.
            $table->unsignedBigInteger('amount');
            $table->string('message', 1000)->nullable();
            $table->json('option_responses')->nullable();
            $table->decimal('estimated_hours', 5, 2)->nullable();
            $table->timestamp('can_start_at')->nullable();

            $table->string('status', 24)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // Satu orang satu penawaran per task.
            $table->unique(['task_id', 'bidder_id']);
            $table->index(['task_id', 'status', 'amount']);
            $table->index(['task_id', 'status', 'created_at']);
            $table->index(['bidder_id', 'status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
