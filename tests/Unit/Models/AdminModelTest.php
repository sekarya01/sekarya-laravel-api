<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AdminAction;
use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Exceptions\Domain\SuperAdminIsProtectedException;
use App\Models\Admin;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Penjaga-penjaga di sekitar tabel `admins`.
 *
 * Diuji lewat JALUR NYATA, bukan lewat factory: factory melewati `$fillable`,
 * sehingga bug "nilai kolom berhak hilang tanpa galat" tidak akan pernah
 * muncul di test yang memakainya. Bug itu sudah pernah terjadi sungguhan di
 * tabel `users`.
 */
final class AdminModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * INI bug yang pernah terjadi di `users`, sekarang dijaga di `admins`.
     *
     * `role` dan `status` tidak fillable, jadi nilai yang dikirim lewat mass
     * assignment HILANG TANPA GALAT — dan default kolomnya yang menentukan
     * hasilnya. Karena default itu keadaan paling sedikit hak, kelalaian
     * berakhir sebagai akun terkunci tanpa kewenangan, bukan sebagai
     * super_admin yang lahir sendiri.
     */
    public function test_role_and_status_cannot_be_mass_assigned_and_default_to_least_privilege(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Penyusup',
            'email' => 'penyusup@sekarya.test',
            'password' => 'RahasiaKuatSekali99!',
            // Dua-duanya diabaikan.
            'role' => AdminRole::SuperAdmin->value,
            'status' => AdminStatus::Active->value,
        ]);

        // Dibaca ULANG dari basis data: yang penting kolomnya, bukan atribut
        // yang masih menempel di objek PHP.
        $fresh = Admin::query()->whereKey($admin->getKey())->sole();

        $this->assertSame(AdminRole::Admin, $fresh->role);
        $this->assertSame(AdminStatus::Suspended, $fresh->status);
        $this->assertFalse($fresh->status->isActive());
        $this->assertFalse($fresh->isSuperAdmin());
    }

    public function test_the_password_is_hashed_and_never_serialised(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Verifikator',
            'email' => 'verif@sekarya.test',
            'password' => 'RahasiaKuatSekali99!',
        ]);

        $stored = (string) Admin::query()->whereKey($admin->getKey())->sole()->password;

        $this->assertNotSame('RahasiaKuatSekali99!', $stored);
        $this->assertTrue(Hash::check('RahasiaKuatSekali99!', $stored));
        $this->assertArrayNotHasKey('password', $admin->toArray());
    }

    // ── super_admin: satu, dan tidak bisa dihapus ───────────────────────────

    public function test_the_super_admin_cannot_be_deleted(): void
    {
        $superAdmin = $this->superAdmin();

        try {
            $superAdmin->delete();
            $this->fail('super_admin seharusnya tidak bisa dihapus');
        } catch (SuperAdminIsProtectedException $e) {
            $this->assertSame('super_admin_protected', $e->errorCode());
            $this->assertSame(403, $e->httpStatus());
        }

        $this->assertDatabaseHas('admins', [
            'id' => $superAdmin->getKey(),
            'deleted_at' => null,
        ]);
    }

    /** Hapus permanen pun ditolak — hook `deleting` ikut jalan di forceDelete. */
    public function test_the_super_admin_cannot_be_force_deleted_either(): void
    {
        $superAdmin = $this->superAdmin();

        $this->expectException(SuperAdminIsProtectedException::class);

        $superAdmin->forceDelete();
    }

    public function test_a_plain_admin_can_be_deleted(): void
    {
        $admin = $this->activeAdmin();

        $admin->delete();

        // Soft delete: barisnya masih ada, karena jejak audit menunjuknya.
        $this->assertSoftDeleted('admins', ['id' => $admin->getKey()]);
        $this->assertNull(Admin::query()->whereKey($admin->getKey())->first());
        $this->assertNotNull(Admin::withTrashed()->whereKey($admin->getKey())->first());
    }

    /**
     * Dijamin BASIS DATA, bukan disiplin kode.
     *
     * Indeks unique atas kolom turunan `super_admin_lock`: ia berisi 's' hanya
     * untuk baris super_admin dan NULL untuk sisanya, dan MySQL tidak
     * menganggap dua NULL bertabrakan. Factory melewati `$fillable`, jadi test
     * ini benar-benar mencoba menulis baris kedua.
     */
    public function test_the_database_refuses_a_second_super_admin(): void
    {
        $this->superAdmin();

        try {
            Admin::factory()->superAdmin()->create(['email' => 'super2@sekarya.test']);
            $this->fail('super_admin kedua seharusnya ditolak basis data');
        } catch (QueryException $e) {
            $this->assertStringContainsString('super_admin_lock', $e->getMessage());
        }

        $this->assertSame(1, Admin::query()->where('role', AdminRole::SuperAdmin)->count());
    }

    public function test_many_plain_admins_are_allowed(): void
    {
        Admin::factory()->count(3)->create();

        $this->assertGreaterThanOrEqual(
            3,
            Admin::query()->where('role', AdminRole::Admin)->count(),
        );
    }

    // ── Kewenangan ──────────────────────────────────────────────────────────

    public function test_may_perform_reads_the_role(): void
    {
        $this->assertTrue($this->superAdmin()->mayPerform(AdminAction::AdminCreated));
        $this->assertFalse($this->activeAdmin()->mayPerform(AdminAction::AdminCreated));
        $this->assertTrue($this->activeAdmin()->mayPerform(AdminAction::PaymentConfirmed));
    }

    /**
     * Gerbang rute dan pemeriksaan di dalam Action membaca SATU sumber.
     *
     * `canManageAdmins()` dipakai middleware EnsureAdminManagesAdmins dan
     * `AdminRole::can()`. Kalau keduanya menulis aturannya sendiri-sendiri,
     * penambahan peran ketiga akan memperbarui satu dan meninggalkan yang
     * lain — dan yang tertinggal adalah yang memutuskan akses.
     */
    public function test_managing_admins_has_one_source_of_truth(): void
    {
        $this->assertTrue($this->superAdmin()->role->canManageAdmins());
        $this->assertFalse($this->activeAdmin()->role->canManageAdmins());

        // Dan `can()` menurunkan jawabannya dari method yang sama.
        $this->assertSame(
            $this->activeAdmin()->role->canManageAdmins(),
            $this->activeAdmin()->mayPerform(AdminAction::AdminCreated),
        );
    }

    /**
     * `mayPerform`, bukan `can` — dan itu bukan selera.
     *
     * `can()` sudah dipakai trait Authorizable milik Laravel dengan tanda
     * tangan berbeda. Menimpanya akan mematahkan seluruh pemeriksaan Gate
     * pada model ini, dan kegagalannya berupa TypeError saat dijalankan,
     * bukan galat saat kompilasi.
     */
    public function test_the_laravel_authorization_api_is_not_broken(): void
    {
        $admin = $this->activeAdmin();

        $this->assertFalse($admin->can('ability-yang-tidak-terdaftar'));
        $this->assertTrue(method_exists($admin, 'mayPerform'));
    }

    public function test_it_gets_a_ulid_and_uses_it_as_route_key(): void
    {
        $admin = $this->activeAdmin();

        $this->assertSame(26, strlen((string) $admin->ulid));
        $this->assertSame('ulid', $admin->getRouteKeyName());
    }
}
