<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\AdminAction;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Support\AdminAuditRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AdminAuditRecorderTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->activeAdmin();
    }

    private function recorder(): AdminAuditRecorder
    {
        return app(AdminAuditRecorder::class);
    }

    public function test_it_derives_the_subject_type_from_the_action(): void
    {
        foreach (AdminAction::cases() as $action) {
            $row = $this->recorder()->record($this->admin, $action, 42);

            $this->assertSame($action->subjectType(), $row->subject_type, $action->value);
            $this->assertSame(42, $row->subject_id);
        }
    }

    /**
     * Alasan yang lebih panjang dari kolomnya DIPOTONG, bukan ditolak.
     *
     * Kolomnya 500 karakter dan MySQL dalam mode strict menolak seluruh
     * INSERT kalau kelebihan — artinya tanpa pemotongan ini, seluruh tindakan
     * pengelola gagal karena alasannya kepanjangan. Batas panjang untuk
     * masukan HTTP tetap ditegakkan FormRequest; ini penjaga untuk pemanggil
     * yang tidak lewat HTTP (command, job).
     */
    public function test_an_over_long_reason_is_truncated_instead_of_failing(): void
    {
        $row = $this->recorder()->record(
            $this->admin,
            AdminAction::UserBanned,
            7,
            str_repeat('a', 900),
        );

        $this->assertSame(500, mb_strlen((string) $row->fresh()->reason));
    }

    public function test_blank_reason_and_ip_are_stored_as_null(): void
    {
        $row = $this->recorder()->record($this->admin, AdminAction::UserReinstated, 7, '   ', '');

        $this->assertNull($row->fresh()->reason);
        $this->assertNull($row->fresh()->ip);
    }

    /** Append-only: tidak ada updated_at, karena jejak yang bisa disunting bukan jejak. */
    public function test_the_row_has_no_updated_at(): void
    {
        $row = $this->recorder()->record($this->admin, AdminAction::PaymentConfirmed, 3);

        $this->assertNull(AdminAuditLog::UPDATED_AT);
        $this->assertNotNull($row->created_at);
        $this->assertArrayNotHasKey('updated_at', $row->getAttributes());
    }

    /**
     * Jejak ditulis DI DALAM transaksi tindakannya, jadi ia ikut dibatalkan
     * kalau tindakannya gagal.
     *
     * Ini kebalikan dari penghitung percobaan kode verifikasi, yang HARUS
     * dicatat di luar transaksi karena percobaannya benar-benar terjadi.
     * Bedanya: jejak ini menyatakan "pengelola menyetujui X", dan kalau X
     * tidak pernah terjadi, jejak itu bohong.
     */
    public function test_a_rolled_back_action_leaves_no_trace(): void
    {
        try {
            DB::transaction(function (): void {
                $this->recorder()->record($this->admin, AdminAction::PaymentConfirmed, 99);

                throw new \RuntimeException('tindakannya gagal');
            });
        } catch (\RuntimeException) {
            // diharapkan
        }

        $this->assertSame(0, AdminAuditLog::query()
            ->forAction(AdminAction::PaymentConfirmed, 99)
            ->count());
    }

    /** Menghapus pelakunya tidak boleh bisa menghapus jejaknya. */
    public function test_the_actor_cannot_be_hard_deleted_while_the_trail_points_at_them(): void
    {
        $this->recorder()->record($this->admin, AdminAction::UserBanned, 5);

        $this->expectException(QueryException::class);

        $this->admin->forceDelete();
    }
}
