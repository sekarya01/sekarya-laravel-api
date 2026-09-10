<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\AdminAction;

/**
 * Ditolak di gerbang `/admin`, bukan di dalam Action.
 *
 * Dua sebab yang berbeda dan keduanya harus ditolak:
 *
 *  - `suspended` — pengelola dinonaktifkan SETELAH login. Token akses hidup
 *    delapan jam, jadi pemeriksaan saat login saja tidak cukup; tanpa
 *    pemeriksaan per permintaan, pencabutan kewenangan baru berlaku delapan
 *    jam kemudian.
 *  - `not_an_admin` — yang datang bukan pemilik token pengelola. Seharusnya
 *    tidak mungkin, karena guard `admin` sudah menyaring jenis pemiliknya —
 *    dan justru karena itu ia diperiksa lagi: kalau guard-nya suatu saat
 *    salah tulis di rute, kegagalannya harus tetap "ditolak", bukan
 *    "diizinkan".
 */
final class AdminAccessDeniedException extends DomainException
{
    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function becauseSuspended(): self
    {
        return new self('Akun pengelola ini dinonaktifkan.', 'suspended');
    }

    public static function becauseNotAnAdmin(): self
    {
        return new self('Endpoint ini hanya untuk pengelola.', 'not_an_admin');
    }

    /**
     * Perannya tidak mencakup tindakan itu.
     *
     * Dilempar Action, bukan hanya ditolak Policy di rute. Pemeriksaan ganda
     * di SATU tempat ini disengaja: Action yang membuat pengelola baru adalah
     * jalur kenaikan hak akses, dan jalur itu juga bisa dipanggil dari
     * command atau job — tempat yang tidak melewati Policy sama sekali.
     */
    public static function becauseRoleCannot(AdminAction $action): self
    {
        return new self(
            'Peran Anda tidak mencakup tindakan ini: '.$action->value.'.',
            'insufficient_role',
        );
    }

    /** Gerbang rute `/admin/admins`. Alasannya sama, sasarannya sekelompok rute. */
    public static function becauseRoleCannotManageAdmins(): self
    {
        return new self(
            'Hanya super_admin yang boleh mengelola akun pengelola.',
            'insufficient_role',
        );
    }

    public function errorCode(): string
    {
        return 'admin_access_denied';
    }

    public function httpStatus(): int
    {
        return 403;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['reason' => $this->reason];
    }
}
