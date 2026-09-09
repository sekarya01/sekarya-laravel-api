<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STUB. Mekanisme pembayaran belum diriset — tabel ini hanya menyediakan
 * status uang agar alur aplikasi bisa dibangun. Yang ditunda (reference/idempotensi,
 * method, gateway_payload, komisi, auto-release, payouts, escrow_holds, ledger)
 * tercatat di docs rancangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('task_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('payer_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 24)->default('pending');
            $table->unsignedBigInteger('amount');

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['payer_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
