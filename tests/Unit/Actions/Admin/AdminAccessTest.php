<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Admin;

use App\Actions\Admin\Access\CreateAdminAction;
use App\Actions\Admin\Access\DeleteAdminAction;
use App\Data\Admin\CreateAdminData;
use App\Enums\AdminAction;
use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Exceptions\Domain\AdminAccessDeniedException;
use App\Exceptions\Domain\SuperAdminIsProtectedException;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Support\TokenIssuer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** super_admin membuat dan menghapus pengelola lain. */
final class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = $this->superAdmin();
    }

    private function data(string $email = 'baru@sekarya.test'): CreateAdminData
    {
        return new CreateAdminData('Verifikator Baru', $email, 'RahasiaKuatSekali99!', '10.0.0.5');
    }

    public function test_the_super_admin_creates_an_active_plain_admin(): void
    {
        $admin = app(CreateAdminAction::class)->handle($this->data(), $this->superAdmin);

        $fresh = Admin::query()->whereKey($admin->getKey())->sole();

        // Perannya DIPAKSA `admin`. Kalau bisa dikirim pemanggil, endpoint ini
        // adalah jalan membuat super_admin kedua.
        $this->assertSame(AdminRole::Admin, $fresh->role);
        $this->assertSame(AdminStatus::Active, $fresh->status);
        $this->assertSame('baru@sekarya.test', $fresh->email);
        $this->assertTrue(Hash::check('RahasiaKuatSekali99!', (string) $fresh->password));
    }

    public function test_creating_writes_an_audit_row_naming_the_creator(): void
    {
        $admin = app(CreateAdminAction::class)->handle($this->data(), $this->superAdmin);

        $row = AdminAuditLog::query()
            ->forAction(AdminAction::AdminCreated, (int) $admin->getKey())
            ->sole();

        $this->assertSame($this->superAdmin->getKey(), (int) $row->admin_id);
        $this->assertSame('baru@sekarya.test', $row->reason);
        $this->assertSame('admin', $row->subject_type);
    }

    /**
     * Peran `admin` tidak bisa membuat pengelola.
     *
     * Diperiksa di Action, bukan hanya oleh Policy di rute: Action ini juga
     * bisa dipanggil dari command atau job, yang tidak melewati Policy.
     */
    public function test_a_plain_admin_cannot_create_another_admin(): void
    {
        $plain = $this->activeAdmin();

        try {
            app(CreateAdminAction::class)->handle($this->data(), $plain);
            $this->fail('peran admin seharusnya tidak bisa membuat pengelola');
        } catch (AdminAccessDeniedException $e) {
            $this->assertSame('admin_access_denied', $e->errorCode());
            $this->assertSame(403, $e->httpStatus());
            $this->assertSame(['reason' => 'insufficient_role'], $e->context());
        }

        $this->assertDatabaseMissing('admins', ['email' => 'baru@sekarya.test']);
    }

    // ── Penghapusan ─────────────────────────────────────────────────────────

    public function test_deleting_soft_deletes_and_revokes_tokens(): void
    {
        $target = $this->activeAdmin();
        app(TokenIssuer::class)->issuePair($target);
        $this->assertSame(2, $target->tokens()->count());

        app(DeleteAdminAction::class)->handle($target, $this->superAdmin, '10.0.0.5');

        // Soft delete: barisnya tetap ada, karena jejak audit menunjuknya.
        $this->assertSoftDeleted('admins', ['id' => $target->getKey()]);
        // Tanpa ini, pengelola yang baru dihapus masih bisa menyetujui
        // pembayaran sampai delapan jam ke depan.
        $this->assertSame(0, $target->tokens()->count());

        $this->assertSame(1, AdminAuditLog::query()
            ->forAction(AdminAction::AdminDeleted, (int) $target->getKey())
            ->count());
    }

    public function test_the_super_admin_cannot_be_deleted_through_the_action_either(): void
    {
        $this->expectException(SuperAdminIsProtectedException::class);

        app(DeleteAdminAction::class)->handle($this->superAdmin, $this->superAdmin);
    }

    /**
     * Penolakan itu membatalkan jejaknya juga.
     *
     * Jejak ditulis sebelum barisnya dihapus (supaya alamatnya masih terbaca),
     * jadi keduanya harus berada dalam satu transaksi — kalau tidak, jejak
     * "dihapus" akan tertinggal untuk penghapusan yang tidak pernah terjadi.
     */
    public function test_a_refused_deletion_leaves_no_audit_row(): void
    {
        try {
            app(DeleteAdminAction::class)->handle($this->superAdmin, $this->superAdmin);
        } catch (SuperAdminIsProtectedException) {
            // diharapkan
        }

        $this->assertSame(0, AdminAuditLog::query()
            ->forAction(AdminAction::AdminDeleted, (int) $this->superAdmin->getKey())
            ->count());
    }

    public function test_a_plain_admin_cannot_delete_anyone(): void
    {
        $plain = $this->activeAdmin();
        $target = $this->activeAdmin();

        $this->expectException(AdminAccessDeniedException::class);

        app(DeleteAdminAction::class)->handle($target, $plain);
    }

    /**
     * Alamat pengelola yang dihapus TETAP terpakai.
     *
     * Indeks unique menghitung baris yang sudah dihapus, dan itu disengaja:
     * `admin_audit_logs` menunjuk baris itu, jadi memberikan alamatnya kepada
     * orang lain akan membuat jejak lama terbaca sebagai perbuatan pemilik
     * alamat yang baru.
     */
    public function test_a_deleted_admins_address_stays_reserved(): void
    {
        $target = $this->activeAdmin(['email' => 'pernah@sekarya.test']);
        app(DeleteAdminAction::class)->handle($target, $this->superAdmin);

        $this->expectException(QueryException::class);

        app(CreateAdminAction::class)->handle($this->data('pernah@sekarya.test'), $this->superAdmin);
    }
}
