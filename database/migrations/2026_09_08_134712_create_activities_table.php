<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('task_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('users')->cascadeOnDelete();
            // Pembayaran yang MEMBUKA activity ini. Keberadaan baris = dana sudah ditahan.
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('status', 24)->default('open');
            $table->unsignedBigInteger('agreed_amount');

            $table->timestamp('opened_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->text('worker_note')->nullable();
            $table->json('proof_photos')->nullable();
            $table->text('poster_note')->nullable();

            $table->timestamps();

            $table->index(['worker_id', 'status', 'created_at']);
            $table->index(['status', 'submitted_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
