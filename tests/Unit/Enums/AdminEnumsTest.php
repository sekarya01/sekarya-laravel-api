<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\AdminAction;
use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use PHPUnit\Framework\TestCase;

/** Enum adalah SSOT kewenangan pengelola; diuji tanpa database. */
final class AdminEnumsTest extends TestCase
{
    // ── AdminRole ───────────────────────────────────────────────────────────

    public function test_super_admin_may_do_everything(): void
    {
        foreach (AdminAction::cases() as $action) {
            $this->assertTrue(AdminRole::SuperAdmin->can($action), $action->value);
        }
    }

    /**
     * Peran `admin` boleh seluruh pekerjaan sehari-hari, dan TIDAK boleh
     * mengelola pengelola.
     *
     * Pemisah itulah satu-satunya alasan peran ini ada: orang yang menilai
     * KTP tidak perlu memegang kunci yang bisa membuat pengelola baru.
     */
    public function test_a_plain_admin_may_do_everything_except_manage_admins(): void
    {
        foreach (AdminAction::cases() as $action) {
            $this->assertSame(
                ! $action->isAdminManagement(),
                AdminRole::Admin->can($action),
                $action->value,
            );
        }

        $this->assertFalse(AdminRole::Admin->can(AdminAction::AdminCreated));
        $this->assertFalse(AdminRole::Admin->can(AdminAction::AdminDeleted));
        $this->assertTrue(AdminRole::Admin->can(AdminAction::PaymentConfirmed));
        $this->assertTrue(AdminRole::Admin->can(AdminAction::VerificationApproved));
    }

    public function test_role_values_are_stable(): void
    {
        // Nilainya tersimpan di kolom `admins.role` dan di jejak audit —
        // menggantinya berarti baris lama menunjuk peran yang tidak ada.
        $this->assertSame(['super_admin', 'admin'], array_column(AdminRole::cases(), 'value'));
        $this->assertSame(['active', 'suspended'], array_column(AdminStatus::cases(), 'value'));
    }

    // ── AdminAction ─────────────────────────────────────────────────────────

    /** `subject_type` harus muat di kolomnya, dan tidak boleh nama kelas PHP. */
    public function test_every_action_maps_to_a_short_subject_slug(): void
    {
        foreach (AdminAction::cases() as $action) {
            $slug = $action->subjectType();

            $this->assertLessThanOrEqual(32, strlen($slug), $action->value);
            $this->assertMatchesRegularExpression('/^[a-z_]+$/', $slug, $action->value);
            $this->assertStringNotContainsString('\\', $slug, $action->value);
        }

        $this->assertSame('user_verification', AdminAction::VerificationApproved->subjectType());
        $this->assertSame('payment', AdminAction::PaymentConfirmed->subjectType());
        $this->assertSame('user', AdminAction::UserBanned->subjectType());
        $this->assertSame('admin', AdminAction::AdminCreated->subjectType());
    }

    /** Setiap tindakan yang merugikan orang harus menuntut alasan. */
    public function test_harmful_actions_require_a_reason(): void
    {
        $this->assertTrue(AdminAction::VerificationRejected->requiresReason());
        $this->assertTrue(AdminAction::VerificationRevoked->requiresReason());
        $this->assertTrue(AdminAction::PaymentRejected->requiresReason());
        $this->assertTrue(AdminAction::UserSuspended->requiresReason());
        $this->assertTrue(AdminAction::UserBanned->requiresReason());

        $this->assertFalse(AdminAction::VerificationApproved->requiresReason());
        $this->assertFalse(AdminAction::VerificationViewed->requiresReason());
        $this->assertFalse(AdminAction::UserReinstated->requiresReason());
    }

    public function test_action_values_are_stable(): void
    {
        $this->assertSame([
            'verification.viewed',
            'verification.approved',
            'verification.rejected',
            'verification.revoked',
            'payment.confirmed',
            'payment.rejected',
            'user.suspended',
            'user.banned',
            'user.reinstated',
            'admin.created',
            'admin.deleted',
        ], array_column(AdminAction::cases(), 'value'));
    }

    // ── AdminStatus ─────────────────────────────────────────────────────────

    public function test_only_active_is_active(): void
    {
        $this->assertTrue(AdminStatus::Active->isActive());
        $this->assertFalse(AdminStatus::Suspended->isActive());
    }

    // ── Ability token ───────────────────────────────────────────────────────

    /**
     * Empat ability, DUA POPULASI. Nilainya tidak boleh saling tumpang tindih:
     * inilah lapis kedua di belakang pemisahan guard, dan lapis itu yang
     * bekerja kalau provider guard di config/auth.php suatu hari hilang.
     */
    public function test_admin_and_user_abilities_never_overlap(): void
    {
        $user = [TokenAbility::Access->value, TokenAbility::Refresh->value];
        $admin = [TokenAbility::AdminAccess->value, TokenAbility::AdminRefresh->value];

        $this->assertSame([], array_intersect($user, $admin));
        $this->assertSame('admin:access', TokenAbility::AdminAccess->value);
        $this->assertSame('admin:refresh', TokenAbility::AdminRefresh->value);
    }

    // ── VerificationStatus ──────────────────────────────────────────────────

    public function test_verification_review_transitions(): void
    {
        $this->assertTrue(VerificationStatus::Pending->canTransitionTo(VerificationStatus::Verified));
        $this->assertTrue(VerificationStatus::Pending->canTransitionTo(VerificationStatus::Rejected));
        $this->assertTrue(VerificationStatus::InReview->canTransitionTo(VerificationStatus::Verified));

        // Yang sudah diberikan harus bisa DICABUT: badge terverifikasi
        // dihitung dari status ini, jadi identitas yang ternyata palsu tanpa
        // jalur pencabutan akan terus menyandang badge itu.
        $this->assertTrue(VerificationStatus::Verified->canTransitionTo(VerificationStatus::Revoked));

        // Dan tidak ada jalan kembali dari penolakan maupun pencabutan:
        // perbaikannya lewat pengajuan ulang, yang meninggalkan riwayatnya.
        $this->assertFalse(VerificationStatus::Rejected->canTransitionTo(VerificationStatus::Verified));
        $this->assertFalse(VerificationStatus::Revoked->canTransitionTo(VerificationStatus::Verified));
        $this->assertFalse(VerificationStatus::Verified->canTransitionTo(VerificationStatus::Verified));
    }

    public function test_awaits_review_is_the_queue_definition(): void
    {
        $this->assertTrue(VerificationStatus::Pending->awaitsReview());
        $this->assertTrue(VerificationStatus::InReview->awaitsReview());
        $this->assertFalse(VerificationStatus::Verified->awaitsReview());
        $this->assertFalse(VerificationStatus::Rejected->awaitsReview());
        $this->assertFalse(VerificationStatus::Revoked->awaitsReview());
    }

    // ── UserStatus, dari sisi pengelola ─────────────────────────────────────

    public function test_admin_may_suspend_and_ban_and_reverse_both(): void
    {
        $this->assertTrue(UserStatus::Active->canBeMovedByAdminTo(UserStatus::Suspended));
        $this->assertTrue(UserStatus::Active->canBeMovedByAdminTo(UserStatus::Banned));
        $this->assertTrue(UserStatus::Suspended->canBeMovedByAdminTo(UserStatus::Active));
        // Pemulihan bisa salah sasaran, jadi ban harus bisa dibatalkan.
        $this->assertTrue(UserStatus::Banned->canBeMovedByAdminTo(UserStatus::Active));
    }

    /**
     * Pengelola TIDAK boleh mengaktifkan akun yang belum verifikasi email
     * dengan cara menaikkan statusnya langsung.
     *
     * Yang menegakkan itu bukan enum ini sendirian — ChangeUserStatusAction
     * yang memilih `pending_verification` sebagai tujuan pemulihan. Yang
     * dijaga di sini: memindahkan `pending_verification` ke `active` bukan
     * transisi yang sah bagi pengelola.
     */
    public function test_admin_cannot_activate_an_unverified_account(): void
    {
        $this->assertFalse(UserStatus::PendingVerification->canBeMovedByAdminTo(UserStatus::Active));
        $this->assertTrue(UserStatus::PendingVerification->canBeMovedByAdminTo(UserStatus::Suspended));
        $this->assertTrue(UserStatus::Suspended->canBeMovedByAdminTo(UserStatus::PendingVerification));
    }

    public function test_moving_a_user_to_the_status_it_already_has_is_not_a_transition(): void
    {
        foreach (UserStatus::cases() as $status) {
            $this->assertFalse($status->canBeMovedByAdminTo($status), $status->value);
        }
    }
}
