<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pendapatan platform dari biaya layanan (G6). Append-only: satu baris
        // per activity yang upahnya dibayar, berisi bruto, fee, dan tarif saat
        // itu. Tanpa tabel ini, fee memotong saldo pekerja tanpa tujuan — uang
        // yang "hilang" dari neraca.
        Schema::create('platform_fee_entries', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('fee_amount');
            // Tarif saat pemotongan, dalam basis poin (500 = 5,00%).
            $table->unsignedInteger('percent_bp');
            $table->timestamps();

            // Satu activity paling banyak satu fee — pengaman idempotensi
            // terakhir kalau WorkerPayout terpanggil dua kali.
            $table->unique('activity_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_entries');
    }
};
