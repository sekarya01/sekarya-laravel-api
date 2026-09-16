<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WalletTopup;

/**
 * Permintaan isi saldo hanya bisa dibatalkan pemiliknya.
 *
 * Gerbangnya Policy, bukan pemeriksaan di dalam Action: aturannya soal OBJEK
 * (permintaan ini milik siapa), dan itu tepat yang dilayani `->can()` di
 * berkas rute — sama seperti `cancel` pada task dan `withdraw` pada bid.
 * Gerbang berbasis PERAN yang memakai middleware adalah hal lain; lihat
 * catatan `admin.manages-admins` di routes/api.php.
 */
final class WalletTopupPolicy
{
    public function cancel(User $user, WalletTopup $topup): bool
    {
        return $user->getKey() === $topup->user_id;
    }
}
