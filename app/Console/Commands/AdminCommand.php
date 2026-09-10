<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Kelola akun pengelola dari baris perintah.
 *
 * INI SATU-SATUNYA cara akun super_admin lahir. Tidak ada endpoint
 * pendaftaran pengelola, dan seeder-nya sengaja tidak ada:
 * `database/seeders` dan `database/schema/sekarya-install.sql` sama-sama
 * dilacak git, jadi kredensial di dalamnya bisa dibaca siapa pun yang membuka
 * repositori — pada akun yang paling berhak di seluruh aplikasi.
 * `tests/Feature/Deployment/InstallSchemaTest.php` bahkan menolak data
 * selain kategori, keahlian, dan riwayat migrasi.
 *
 * Kredensialnya karena itu diambil dari environment (`SEKARYA_SUPER_ADMIN_*`)
 * atau dari opsi perintah ini.
 */
final class AdminCommand extends Command
{
    protected $signature = 'sekarya:admin
        {action=create : create | list | suspend | activate}
        {--name= : Nama, untuk create}
        {--email= : Alamat email; wajib untuk suspend/activate}
        {--password= : Sandi. Dikosongkan berarti ditanyakan, atau diambil dari env untuk super_admin}
        {--role=super_admin : super_admin | admin}';

    protected $description = 'Kelola akun pengelola (super_admin & admin)';

    public function handle(): int
    {
        return match ((string) $this->argument('action')) {
            'create' => $this->create(),
            'list' => $this->list(),
            'suspend' => $this->setStatus(AdminStatus::Suspended),
            'activate' => $this->setStatus(AdminStatus::Active),
            default => $this->invalidAction(),
        };
    }

    private function create(): int
    {
        $role = AdminRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->components->error('Peran harus super_admin atau admin.');

            return self::FAILURE;
        }

        // Satu super_admin. Diperiksa di sini supaya jawabannya penjelasan,
        // bukan galat #1062 dari indeks unique — penjaga sebenarnya tetap
        // indeks itu, karena perintah ini bukan satu-satunya penulis tabel.
        if ($role->isSuperAdmin()) {
            $existing = Admin::query()->where('role', AdminRole::SuperAdmin)->first();

            if ($existing !== null) {
                $this->components->error(sprintf(
                    'super_admin sudah ada: %s. Hanya boleh satu, dan ia tidak bisa dihapus.',
                    $existing->email,
                ));
                $this->line('  Ganti sandinya: hapus baris tokennya lalu jalankan ulang '
                    .'`sekarya:admin create` setelah barisnya diubah langsung di basis data.');

                return self::FAILURE;
            }
        }

        $defaults = (array) config('sekarya.admin.super_admin');

        $name = (string) ($this->option('name')
            ?: ($role->isSuperAdmin() ? ($defaults['name'] ?? '') : '')
            ?: $this->ask('Nama'));

        $email = mb_strtolower(trim((string) ($this->option('email')
            ?: ($role->isSuperAdmin() ? ($defaults['email'] ?? '') : '')
            ?: $this->ask('Email'))));

        $password = (string) ($this->option('password')
            ?: ($role->isSuperAdmin() ? ($defaults['password'] ?? '') : '')
            ?: $this->secret('Kata sandi'));

        $failure = $this->validateInput($name, $email, $password);

        if ($failure !== null) {
            $this->components->error($failure);

            return self::FAILURE;
        }

        $admin = new Admin([
            'name' => $name,
            'email' => $email,
            // Cast `hashed` di model yang menghashnya.
            'password' => $password,
        ]);

        // Di luar mass assignment: dua kolom pembawa hak akses.
        $admin->role = $role;
        $admin->status = AdminStatus::Active;
        $admin->save();

        $this->components->info(sprintf('%s dibuat: %s', $role->label(), $admin->email));
        $this->newLine();
        $this->components->twoColumnDetail('Peran', $admin->role->value);
        $this->components->twoColumnDetail('Status', $admin->status->value);
        $this->components->twoColumnDetail('Masuk lewat', 'POST /api/v1/admin/auth/login');
        $this->newLine();
        $this->components->warn(
            'Sandinya TIDAK ditampilkan dan tidak bisa dibaca lagi — yang tersimpan hanya hash-nya.',
        );

        return self::SUCCESS;
    }

    /**
     * Aturan sandi pengelola, dan mengapa dua tingkat.
     *
     * Panjang minimum berlaku di semua lingkungan. Daftar sandi terlarang
     * (`config/sekarya.php`) hanya ditegakkan di produksi: nilainya adalah
     * sandi contoh yang ikut terlacak git, dan memakainya di laptop memang
     * yang membuat `bash docs/smoke.sh` bisa berjalan sendiri.
     */
    private function validateInput(string $name, string $email, string $password): ?string
    {
        $min = (int) config('sekarya.admin.min_password_length');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email:rfc', 'max:180', 'unique:admins,email'],
                'password' => ['required', Password::min($min)],
            ],
        );

        if ($validator->fails()) {
            return (string) $validator->errors()->first();
        }

        $forbidden = (array) config('sekarya.admin.forbidden_passwords');

        if (in_array($password, $forbidden, true)) {
            if (app()->environment('production')) {
                return 'Sandi itu ada di daftar contoh yang terlacak git. '
                    .'Di produksi ia bukan kredensial — ganti sebelum melanjutkan.';
            }

            $this->components->warn(
                'Sandi contoh dipakai. Aman untuk lokal; di produksi perintah ini akan menolaknya.',
            );
        }

        return null;
    }

    private function list(): int
    {
        $admins = Admin::query()->orderBy('role')->orderBy('email')->get();

        if ($admins->isEmpty()) {
            $this->components->warn('Belum ada akun pengelola. Buat super_admin: sekarya:admin create');

            return self::SUCCESS;
        }

        $this->table(
            ['Email', 'Nama', 'Peran', 'Status', 'Terakhir masuk'],
            $admins->map(fn (Admin $a): array => [
                $a->email,
                $a->name,
                $a->role->value,
                $a->status->value,
                $a->last_login_at?->toDateTimeString() ?? '-',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function setStatus(AdminStatus $status): int
    {
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Email'))));

        $admin = Admin::query()->where('email', $email)->first();

        if (! $admin instanceof Admin) {
            $this->components->error('Tidak ada pengelola dengan alamat itu.');

            return self::FAILURE;
        }

        // super_admin tidak bisa dinonaktifkan: ia satu-satunya yang bisa
        // membuat pengelola baru, jadi menonaktifkannya berarti tidak ada lagi
        // jalan memulihkan akses pengelola dari dalam aplikasi.
        if ($admin->role->isSuperAdmin() && ! $status->isActive()) {
            $this->components->error(
                'super_admin tidak bisa dinonaktifkan — tidak akan ada lagi yang bisa membuat pengelola baru.',
            );

            return self::FAILURE;
        }

        $admin->forceFill(['status' => $status])->save();

        // Status saja tidak menghentikan siapa pun: token akses hidup delapan
        // jam dan tidak menyimpan status di dalamnya.
        if (! $status->isActive()) {
            $admin->tokens()->delete();
            $this->line('  Token dicabut.');
        }

        $this->components->info(sprintf('%s sekarang %s.', $admin->email, $status->value));

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->components->error('Aksi harus salah satu dari: create, list, suspend, activate.');

        return self::FAILURE;
    }
}
