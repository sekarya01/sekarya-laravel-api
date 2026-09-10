<?php

declare(strict_types=1);

namespace App\Actions\Admin\Access;

use App\Data\Admin\CreateAdminData;
use App\Enums\AdminAction;
use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Exceptions\Domain\AdminAccessDeniedException;
use App\Models\Admin;
use App\Support\AdminAuditRecorder;
use Illuminate\Database\ConnectionInterface;

/**
 * super_admin membuat akun pengelola baru.
 *
 * Perannya SELALU `admin`, dipaksa di sini dan tidak bisa dikirim klien.
 * Tanpa itu, endpoint ini adalah jalan membuat super_admin kedua — dan
 * satu-satunya yang menahannya cuma aturan validasi, yang berubah setiap kali
 * ada orang menambah field.
 *
 * Kewenangan pemanggil diperiksa DI SINI juga, bukan hanya oleh Policy di
 * rute. Pemeriksaan ganda hanya dilakukan pada dua Action ini di seluruh
 * aplikasi, dengan alasan: ini jalur kenaikan hak akses, dan ia juga bisa
 * dipanggil dari command atau job — tempat yang tidak melewati Policy sama
 * sekali.
 */
final class CreateAdminAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function handle(CreateAdminData $data, Admin $actor): Admin
    {
        if (! $actor->mayPerform(AdminAction::AdminCreated)) {
            throw AdminAccessDeniedException::becauseRoleCannot(AdminAction::AdminCreated);
        }

        return $this->db->transaction(function () use ($data, $actor): Admin {
            $admin = new Admin([
                'name' => $data->name,
                'email' => $data->email,
                // Cast `hashed` di model yang menghashnya. Sandi mentah tidak
                // pernah sampai ke kolomnya.
                'password' => $data->password,
            ]);

            // Di luar mass assignment: keduanya kolom pembawa hak akses.
            // Bawaan kolomnya `admin` + `suspended` (paling sedikit hak);
            // jalur ini menyetelnya sadar, karena akunnya memang sengaja
            // dibuat oleh orang yang berhak.
            $admin->role = AdminRole::Admin;
            $admin->status = AdminStatus::Active;
            $admin->save();

            $this->audit->record(
                $actor,
                AdminAction::AdminCreated,
                (int) $admin->getKey(),
                $data->email,
                $data->ip,
            );

            return $admin;
        });
    }
}
