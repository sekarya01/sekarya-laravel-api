<?php

declare(strict_types=1);

namespace Tests\Feature\Web\SuperAdmin;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kelola akun pengelola: peran baru selalu admin, super_admin dilindungi,
 * hapus = soft delete. Validasi gagal membuka lagi drawer kanan.
 */
final class AdminAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_menampilkan_pengelola(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');

        $this->get(route('super_admin.admins.index'))
            ->assertOk()
            ->assertSee('Buat pengelola', false);
    }

    public function test_membuat_admin_berperan_admin(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');

        $this->post(route('super_admin.admins.store'), [
            'name' => 'Verifikator Web',
            'email' => 'verifweb@sekarya.test',
            'password' => 'RahasiaKuat99!!',
            'password_confirmation' => 'RahasiaKuat99!!',
        ])->assertSessionHas('status');

        $admin = Admin::query()->where('email', 'verifweb@sekarya.test')->firstOrFail();

        $this->assertTrue($admin->role->isSuperAdmin() === false);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'admin.created',
            'subject_id' => $admin->getKey(),
        ]);
    }

    public function test_validasi_gagal_membuka_lagi_drawer(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');

        $this->post(route('super_admin.admins.store'), [
            'name' => 'X',
            'email' => 'bukan-email',
            'password' => 'pendek',
            'password_confirmation' => 'beda',
        ])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertSame('create-admin', session('open_modal'));
        $this->assertDatabaseMissing('admins', ['email' => 'bukan-email']);
    }

    public function test_menghapus_admin_soft_delete(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $target = $this->activeAdmin();

        $this->delete(route('super_admin.admins.destroy', $target->ulid))
            ->assertSessionHas('status');

        $this->assertSoftDeleted('admins', ['id' => $target->getKey()]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'admin.deleted',
            'subject_id' => $target->getKey(),
        ]);
    }

    public function test_super_admin_tidak_bisa_dihapus(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $super = Admin::query()->where('role', AdminRole::SuperAdmin)->firstOrFail();

        $this->delete(route('super_admin.admins.destroy', $super->ulid))
            ->assertSessionHasErrors('action');

        $this->assertDatabaseHas('admins', [
            'id' => $super->getKey(),
            'deleted_at' => null,
        ]);
    }
}
