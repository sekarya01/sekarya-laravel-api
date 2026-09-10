<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\BaseResource;
use App\Models\Admin;
use Illuminate\Http\Request;

/**
 * Akun pengelola.
 *
 * `last_login_ip` TIDAK keluar. Ia disimpan untuk penyelidikan kalau ada akun
 * yang dicurigai dibobol, dan itu pekerjaan yang dilakukan di basis data —
 * bukan alasan untuk menaruh alamat jaringan rekan kerja di respons API yang
 * bisa dibuka di sepuluh tempat.
 *
 * @mixin Admin
 */
final class AdminResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'status' => $this->status->value,
            // Kunci yang menggerakkan UI: hanya super_admin yang melihat menu
            // pengelola, dan hanya dia yang tidak punya tombol hapus.
            'is_super_admin' => $this->role->isSuperAdmin(),
            'last_login_at' => $this->iso($this->last_login_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
