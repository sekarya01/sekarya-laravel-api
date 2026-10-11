<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Enums\AdminAction;
use App\Http\Requests\Api\V1\Admin\ModerateUserRequest;
use Illuminate\Http\Request;

/**
 * Tindakan moderasi atas satu akun pengguna.
 *
 * DTO ini membawa TINDAKANNYA, bukan status tujuannya. Tujuan `reinstate`
 * tidak bisa ditentukan di sini: akun yang belum pernah memverifikasi email
 * harus kembali ke `pending_verification`, bukan ke `active`, dan yang tahu
 * hal itu adalah baris penggunanya — jadi yang memutuskannya
 * ChangeUserStatusAction.
 */
final readonly class ChangeUserStatusData
{
    private function __construct(
        public AdminAction $action,
        public ?string $reason,
        public ?string $ip,
    ) {}

    public static function suspend(ModerateUserRequest $request): self
    {
        return self::suspended($request->string('reason')->value(), $request->ip());
    }

    public static function ban(ModerateUserRequest $request): self
    {
        return self::banned($request->string('reason')->value(), $request->ip());
    }

    public static function reinstate(Request $request): self
    {
        return new self(AdminAction::UserReinstated, null, $request->ip());
    }

    /**
     * Versi nilai-polos untuk dasbor web (validasinya sendiri, tanpa
     * FormRequest API). Tetap konstruktor bernama: tindakannya ditentukan
     * nama fungsi, bukan oleh pemanggil.
     */
    public static function suspended(string $reason, ?string $ip): self
    {
        return new self(AdminAction::UserSuspended, $reason, $ip);
    }

    public static function banned(string $reason, ?string $ip): self
    {
        return new self(AdminAction::UserBanned, $reason, $ip);
    }
}
