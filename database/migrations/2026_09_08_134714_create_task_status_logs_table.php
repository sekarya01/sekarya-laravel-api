<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Append-only. Tidak ada updated_at: jejak yang bisa diedit bukan jejak. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_status_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('actor_type', 12);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['task_id', 'created_at']);
            $table->index(['to_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_logs');
    }
};
