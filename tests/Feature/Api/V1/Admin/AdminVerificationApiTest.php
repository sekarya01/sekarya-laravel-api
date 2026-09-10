<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminAction;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $user;

    private UserVerification $verification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->activeAdmin();
        $this->user = $this->activeUser(['name' => 'Budi Prasetyo']);
        $this->verification = UserVerification::factory()->create([
            'user_id' => $this->user->getKey(),
            'document_number_enc' => '3271012345678901',
            'name_on_document' => 'BUDI PRASETYO',
        ]);
    }

    // ── Antrean ─────────────────────────────────────────────────────────────

    public function test_the_queue_lists_pending_submissions_with_the_submitter(): void
    {
        $this->asAdmin($this->admin)->getJson(route('v1.admin.verifications.index'))
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.awaits_review', true)
            ->assertJsonPath('data.0.has_id_card_photo', true)
            ->assertJsonPath('data.0.user.name', 'Budi Prasetyo')
            ->assertJsonStructure([
                'data' => [['id', 'type', 'status', 'awaits_review', 'has_id_card_photo',
                    'has_selfie_photo', 'face_match_score', 'submitted_at', 'reviewed_at', 'user']],
            ]);
    }

    /**
     * DAFTAR tidak pernah membawa NIK, path foto, atau nomor rekening.
     *
     * Dijaga oleh kelas Resource yang berbeda, bukan oleh sebuah penanda:
     * AdminVerificationResource tidak punya kode untuk mengeluarkannya.
     */
    public function test_the_queue_never_carries_the_document_number_or_the_photo_paths(): void
    {
        $body = $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.verifications.index'))
            ->assertOk()
            ->content();

        foreach ([
            '3271012345678901',
            'document_number',
            'id_card_photo_path',
            'selfie_photo_path',
            'document_number_hash',
            'verifications/',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $body, $needle);
        }
    }

    public function test_the_queue_can_be_filtered_by_status_and_type(): void
    {
        UserVerification::factory()->verified()->create(['user_id' => $this->activeUser()->getKey()]);
        UserVerification::factory()->create([
            'user_id' => $this->activeUser()->getKey(),
            'type' => VerificationType::BankAccount,
        ]);

        $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.verifications.index', ['status' => 'verified']))
            ->assertOk()
            ->assertJsonPath('data.0.status', 'verified');

        $types = $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.verifications.index', ['type' => 'bank_account']))
            ->assertOk()
            ->json('data.*.type');

        $this->assertSame(['bank_account'], array_unique($types));
    }

    public function test_an_unknown_status_filter_is_rejected(): void
    {
        $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.verifications.index', ['status' => 'entahlah']))
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['status']]);
    }

    // ── Detail: satu-satunya tempat NIK keluar ──────────────────────────────

    public function test_the_detail_discloses_the_document_number_to_the_reviewer(): void
    {
        $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.verifications.show', $this->verification))
            ->assertOk()
            ->assertJsonPath('data.document_number', '3271012345678901')
            ->assertJsonPath('data.name_on_document', 'BUDI PRASETYO')
            ->assertJsonPath('data.user.email', $this->user->email);
    }

    /** Yang TETAP tidak keluar, bahkan di detail. */
    public function test_the_detail_still_hides_the_hash_and_the_photo_paths(): void
    {
        $body = $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.verifications.show', $this->verification))
            ->assertOk()
            ->content();

        foreach (['document_number_hash', 'id_card_photo_path', 'selfie_photo_path', 'verifications/'] as $needle) {
            $this->assertStringNotContainsString($needle, $body, $needle);
        }
    }

    /** Setiap pembacaan detail meninggalkan jejak. */
    public function test_opening_the_detail_is_recorded_in_the_audit_trail(): void
    {
        $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.verifications.show', $this->verification))
            ->assertOk();

        $row = AdminAuditLog::query()
            ->forAction(AdminAction::VerificationViewed, (int) $this->verification->getKey())
            ->sole();

        $this->assertSame($this->admin->getKey(), (int) $row->admin_id);
    }

    public function test_a_bank_account_detail_discloses_the_account_number(): void
    {
        $bank = UserVerification::factory()->create([
            'user_id' => $this->user->getKey(),
            'type' => VerificationType::BankAccount,
            'document_number_enc' => null,
            'bank_code' => '014',
            'account_number_enc' => '1234567890',
            'account_holder_name' => 'BUDI PRASETYO',
        ]);

        $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.verifications.show', $bank))
            ->assertOk()
            ->assertJsonPath('data.bank_code', '014')
            ->assertJsonPath('data.account_number', '1234567890')
            ->assertJsonPath('data.account_holder_name', 'BUDI PRASETYO');
    }

    // ── Keputusan ───────────────────────────────────────────────────────────

    public function test_approving_verifies_the_identity(): void
    {
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.verifications.approve', $this->verification))
            ->assertOk()
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.reviewed_by.id', $this->admin->ulid);

        // Terlihat oleh pemilik akunnya sendiri.
        $this->asUser($this->user)->getJson(route('v1.me.verifications.index'))
            ->assertOk()
            ->assertJsonPath('data.0.status', 'verified')
            ->assertJsonPath('data.0.is_verified', true);
    }

    public function test_rejecting_requires_a_usable_reason(): void
    {
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.verifications.reject', $this->verification))
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['reason']]);

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.verifications.reject', $this->verification), ['reason' => 'no'])
            ->assertUnprocessable();

        $this->assertSame(
            VerificationStatus::Pending,
            $this->verification->refresh()->status,
        );
    }

    public function test_rejecting_tells_the_user_what_to_fix(): void
    {
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.verifications.reject', $this->verification), [
                'reason' => 'Foto KTP tidak terbaca, silakan unggah ulang.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->asUser($this->user)->getJson(route('v1.me.verifications.index'))
            ->assertOk()
            ->assertJsonPath('data.0.rejection_reason', 'Foto KTP tidak terbaca, silakan unggah ulang.');
    }

    public function test_revoking_takes_a_granted_verification_back(): void
    {
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.verifications.approve', $this->verification))
            ->assertOk();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.verifications.revoke', $this->verification), [
                'reason' => 'Dokumen ternyata milik orang lain.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->assertFalse($this->user->refresh()->isIdentityVerified());
    }

    public function test_approving_a_rejected_submission_is_refused_with_a_machine_code(): void
    {
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.verifications.reject', $this->verification), [
                'reason' => 'Fotonya kabur sekali, tidak bisa dibaca.',
            ])->assertOk();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.verifications.approve', $this->verification))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_status_transition');
    }

    /** Pengguna tidak bisa menyetujui verifikasinya sendiri. */
    public function test_a_user_cannot_reach_the_review_endpoints(): void
    {
        $this->asUser($this->user)
            ->postJson(route('v1.admin.verifications.approve', $this->verification))
            ->assertUnauthorized();

        $this->assertSame(VerificationStatus::Pending, $this->verification->refresh()->status);
    }
}
