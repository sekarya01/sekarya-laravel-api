<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kode unik 3 digit untuk pencocokan mutasi manual (B14).
 *
 * Pengguna mentransfer `transfer_amount = amount + unique_code`, dan pengelola
 * mencocokkan nominal PERSIS itu di mutasi rekening — tidak lagi menebak dari
 * nominal bulat yang bisa sama antarpermintaan. Baris lama di-backfill:
 * `transfer_amount = amount`, `unique_code = 0` (tanpa kode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_topups', function (Blueprint $table): void {
            $table->unsignedSmallInteger('unique_code')->default(0)->after('amount');
            $table->unsignedBigInteger('transfer_amount')->default(0)->after('unique_code');
        });

        DB::table('wallet_topups')->update(['transfer_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('wallet_topups', function (Blueprint $table): void {
            $table->dropColumn(['unique_code', 'transfer_amount']);
        });
    }
};
