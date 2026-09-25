<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\VerificationType;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;

/**
 * Menyusulkan `account_number_last4` untuk rekening yang diajukan sebelum
 * kolom itu ada.
 *
 * Dipanggil migrasi `..._add_account_number_last4_to_user_worker_verifications`
 * supaya ikut jalan di `php artisan migrate` — tanpa langkah manual di server
 * tanpa SSH. Aman diulang: hanya baris yang kolomnya masih NULL yang disentuh.
 *
 * Lewat query builder, bukan model: cast `encrypted` di model akan melempar
 * pada satu baris yang tidak bisa didekripsi dan menghentikan seluruh
 * migrasi. Di sini baris itu dilewati dan tetap NULL.
 */
final class BankAccountLast4Backfill
{
    private const string TABLE = 'user_worker_verifications';

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Encrypter $encrypter,
    ) {}

    /** @return int jumlah baris yang terisi */
    public function run(): int
    {
        $filled = 0;

        $this->db->table(self::TABLE)
            ->where('type', VerificationType::BankAccount->value)
            ->whereNotNull('account_number_enc')
            ->whereNull('account_number_last4')
            ->select(['id', 'account_number_enc'])
            ->chunkById(200, function ($rows) use (&$filled): void {
                foreach ($rows as $row) {
                    try {
                        // Cast `encrypted` = decrypt tanpa unserialize.
                        $number = $this->encrypter->decrypt((string) $row->account_number_enc, false);
                    } catch (DecryptException) {
                        continue;
                    }

                    $last4 = BankAccountNumber::lastFour(is_string($number) ? $number : null);

                    if ($last4 === null) {
                        continue;
                    }

                    $this->db->table(self::TABLE)
                        ->where('id', $row->id)
                        ->update(['account_number_last4' => $last4]);

                    $filled++;
                }
            });

        return $filled;
    }
}
