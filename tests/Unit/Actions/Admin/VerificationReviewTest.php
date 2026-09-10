<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Admin;

use App\Actions\Admin\Verification\ListVerificationQueueAction;
use App\Actions\Admin\Verification\ReviewVerificationAction;
use App\Actions\Admin\Verification\ViewVerificationAction;
use App\Data\Admin\ReviewVerificationData;
use App\Data\Admin\VerificationQueueData;
use App\Data\CursorPageData;
use App\Enums\AdminAction;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Http\Requests\Api\V1\Admin\ReviewVerificationRequest;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class VerificationReviewTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $user;

    private UserVerification $verification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->activeAdmin();
        $this->user = $this->activeUser();
        $this->verification = UserVerification::factory()->create([
            'user_id' => $this->user->getKey(),
        ]);
    }

    private function request(array $input = []): Request
    {
        $request = Request::create('/', 'POST', $input);
        $request->server->set('REMOTE_ADDR', '10.0.0.5');

        return $request;
    }

    private function review(ReviewVerificationData $data): UserVerification
    {
        return app(ReviewVerificationAction::class)->handle($this->verification, $this->admin, $data);
    }

    // ── Persetujuan ─────────────────────────────────────────────────────────

    public function test_approving_marks_it_verified_and_names_the_reviewer(): void
    {
        $result = $this->review(ReviewVerificationData::approve($this->request()));

        $this->assertSame(VerificationStatus::Verified, $result->status);
        $this->assertTrue($result->status->isVerified());

        // Barisnya, bukan objek di memori.
        $this->assertDatabaseHas('user_worker_verifications', [
            'id' => $this->verification->getKey(),
            'status' => VerificationStatus::Verified->value,
            'reviewed_by' => $this->admin->getKey(),
            'rejection_reason' => null,
        ]);
        $this->assertNotNull($result->reviewed_at);

        // `reviewed_by` menunjuk tabel `admins`, dijamin foreign key.
        $this->assertTrue($result->reviewer->is($this->admin));
    }

    /** Badge terverifikasi dihitung dari status ini, bukan disimpan. */
    public function test_approving_turns_on_the_identity_badge(): void
    {
        $this->assertFalse($this->user->isIdentityVerified());

        $this->review(ReviewVerificationData::approve($this->request()));

        $this->assertTrue($this->user->refresh()->isIdentityVerified());
    }

    public function test_approving_writes_an_audit_row(): void
    {
        $this->review(ReviewVerificationData::approve($this->request()));

        $row = AdminAuditLog::query()
            ->forAction(AdminAction::VerificationApproved, (int) $this->verification->getKey())
            ->sole();

        $this->assertSame($this->admin->getKey(), (int) $row->admin_id);
        $this->assertSame('user_verification', $row->subject_type);
        $this->assertSame('10.0.0.5', $row->ip);
        $this->assertNull($row->reason);
    }

    // ── Penolakan ───────────────────────────────────────────────────────────

    public function test_rejecting_stores_the_reason_the_user_will_read(): void
    {
        $result = $this->review(ReviewVerificationData::reject(
            $this->rejectRequest('Foto KTP tidak terbaca, silakan unggah ulang.'),
        ));

        $this->assertSame(VerificationStatus::Rejected, $result->status);
        $this->assertDatabaseHas('user_worker_verifications', [
            'id' => $this->verification->getKey(),
            'status' => VerificationStatus::Rejected->value,
            'rejection_reason' => 'Foto KTP tidak terbaca, silakan unggah ulang.',
        ]);
        $this->assertFalse($this->user->refresh()->isIdentityVerified());

        $this->assertSame('Foto KTP tidak terbaca, silakan unggah ulang.', AdminAuditLog::query()
            ->forAction(AdminAction::VerificationRejected, (int) $this->verification->getKey())
            ->sole()->reason);
    }

    /**
     * Alasan penolakan TIDAK boleh tertinggal pada baris yang akhirnya
     * disetujui — pengguna akan membacanya sebagai penolakan yang masih
     * berlaku.
     */
    public function test_approving_after_a_revocation_cycle_leaves_no_stale_reason(): void
    {
        $this->verification->forceFill([
            'status' => VerificationStatus::Verified,
            'rejection_reason' => 'alasan lama',
        ])->save();

        $result = $this->review(ReviewVerificationData::revoke(
            $this->rejectRequest('Dokumen ternyata milik orang lain.'),
        ));

        $this->assertSame(VerificationStatus::Revoked, $result->status);
        $this->assertNull($result->rejection_reason);
        $this->assertSame('Dokumen ternyata milik orang lain.', $result->revoked_reason);
        $this->assertNotNull($result->revoked_at);
    }

    // ── Transisi ────────────────────────────────────────────────────────────

    public function test_a_rejected_submission_cannot_be_approved_afterwards(): void
    {
        $this->review(ReviewVerificationData::reject($this->rejectRequest('Fotonya kabur sekali.')));

        try {
            $this->review(ReviewVerificationData::approve($this->request()));
            $this->fail('penolakan seharusnya tidak bisa dibalik di baris yang sama');
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame(['from' => 'rejected', 'to' => 'verified'], $e->context());
        }
    }

    public function test_approving_twice_is_rejected(): void
    {
        $this->review(ReviewVerificationData::approve($this->request()));

        $this->expectException(InvalidStatusTransitionException::class);

        $this->review(ReviewVerificationData::approve($this->request()));
    }

    // ── Antrean ─────────────────────────────────────────────────────────────

    /** Paling lama menunggu di depan — kebalikan dari endpoint daftar lain. */
    public function test_the_queue_puts_the_longest_waiting_first(): void
    {
        $older = UserVerification::factory()->create([
            'user_id' => $this->activeUser()->getKey(),
            'submitted_at' => now()->subDays(3),
        ]);

        $ids = app(ListVerificationQueueAction::class)
            ->handle(new VerificationQueueData(new CursorPageData(10)))
            ->pluck('id')
            ->all();

        $this->assertSame(
            [$older->getKey(), $this->verification->getKey()],
            array_values(array_intersect($ids, [$older->getKey(), $this->verification->getKey()])),
        );
    }

    /** Bawaannya yang MENUNGGU, bukan seluruh riwayat. */
    public function test_the_queue_hides_rows_that_were_already_decided(): void
    {
        $decided = UserVerification::factory()->verified()->create([
            'user_id' => $this->activeUser()->getKey(),
        ]);

        $ids = app(ListVerificationQueueAction::class)
            ->handle(new VerificationQueueData(new CursorPageData(50)))
            ->pluck('id')
            ->all();

        $this->assertContains($this->verification->getKey(), $ids);
        $this->assertNotContains($decided->getKey(), $ids);

        // Dan riwayatnya tetap bisa diminta secara eksplisit.
        $verifiedIds = app(ListVerificationQueueAction::class)
            ->handle(new VerificationQueueData(new CursorPageData(50), VerificationStatus::Verified))
            ->pluck('id')
            ->all();

        $this->assertContains($decided->getKey(), $verifiedIds);
        $this->assertNotContains($this->verification->getKey(), $verifiedIds);
    }

    public function test_the_queue_can_be_filtered_by_type(): void
    {
        $bank = UserVerification::factory()->create([
            'user_id' => $this->activeUser()->getKey(),
            'type' => VerificationType::BankAccount,
        ]);

        $ids = app(ListVerificationQueueAction::class)
            ->handle(new VerificationQueueData(
                new CursorPageData(50),
                null,
                VerificationType::BankAccount,
            ))
            ->pluck('id')
            ->all();

        $this->assertContains($bank->getKey(), $ids);
        $this->assertNotContains($this->verification->getKey(), $ids);
    }

    // ── Pembacaan detail ────────────────────────────────────────────────────

    /**
     * Membuka detail MENULIS jejak.
     *
     * Detail itulah satu-satunya tempat NIK keluar terbaca; tanpa baris ini,
     * "siapa pernah membuka data siapa" tidak terjawab oleh apa pun.
     */
    public function test_opening_the_detail_is_recorded(): void
    {
        app(ViewVerificationAction::class)->handle($this->verification, $this->admin, '10.0.0.5');

        $row = AdminAuditLog::query()
            ->forAction(AdminAction::VerificationViewed, (int) $this->verification->getKey())
            ->sole();

        $this->assertSame($this->admin->getKey(), (int) $row->admin_id);
        $this->assertSame('10.0.0.5', $row->ip);
    }

    private function rejectRequest(string $reason): ReviewVerificationRequest
    {
        $request = ReviewVerificationRequest::create(
            '/', 'POST', ['reason' => $reason],
        );
        $request->server->set('REMOTE_ADDR', '10.0.0.5');

        return $request;
    }
}
