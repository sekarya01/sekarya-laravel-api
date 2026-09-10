<?php

declare(strict_types=1);

namespace App\Actions\Admin\Access;

use App\Enums\AdminAction;
use App\Exceptions\Domain\AdminAccessDeniedException;
use App\Models\Admin;
use App\Support\AdminAuditRecorder;
use App\Support\TokenIssuer;
use Illuminate\Database\ConnectionInterface;

/**
 * super_admin menghapus akun pengelola.
 *
 * SOFT DELETE, bukan hapus permanen: `admin_audit_logs` menunjuk barisnya,
 * dan jejak "siapa menyetujui pembayaran ini" yang menunjuk baris yang sudah
 * hilang tidak bisa dibaca lagi. Foreign key-nya `restrictOnDelete`, jadi
 * hapus permanen memang akan ditolak basis data.
 *
 * Akibat yang disengaja: alamat emailnya tetap terpakai. Indeks unique
 * menghitung baris yang sudah dihapus, jadi alamat itu tidak bisa diberikan
 * kepada orang lain — kalau bisa, jejak lama akan terbaca sebagai perbuatan
 * pemilik alamat yang baru.
 *
 * Token dicabut. Tanpa itu, pengelola yang baru dihapus masih bisa
 * menyetujui pembayaran sampai delapan jam ke depan.
 *
 * super_admin sendiri tidak bisa dihapus — dijaga hook `deleting` di model,
 * jadi jalur mana pun (command, tinker, Action lain) ikut terjaga. Itu
 * sekaligus yang membuat "menghapus diri sendiri" mustahil di sini:
 * satu-satunya peran yang boleh memanggil Action ini adalah super_admin.
 */
final class DeleteAdminAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TokenIssuer $tokens,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function handle(Admin $target, Admin $actor, ?string $ip = null): void
    {
        if (! $actor->mayPerform(AdminAction::AdminDeleted)) {
            throw AdminAccessDeniedException::becauseRoleCannot(AdminAction::AdminDeleted);
        }

        $this->db->transaction(function () use ($target, $actor, $ip): void {
            // Jejak ditulis SEBELUM barisnya dihapus, supaya alamat yang
            // dicatat masih bisa dibaca dari baris itu tanpa menebak.
            $this->audit->record(
                $actor,
                AdminAction::AdminDeleted,
                (int) $target->getKey(),
                $target->email,
                $ip,
            );

            $this->tokens->revokeAll($target);

            // Melempar SuperAdminIsProtectedException kalau sasarannya
            // super_admin — dan karena itu di dalam transaksi, jejak yang
            // baru ditulis di atas ikut dibatalkan.
            $target->delete();
        });
    }
}
