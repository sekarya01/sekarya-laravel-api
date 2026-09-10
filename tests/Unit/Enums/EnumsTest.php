<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\ActivityStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewerRole;
use App\Enums\TaskStatus;
use App\Enums\TokenAbility;
use App\Enums\UserActiveMode;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use PHPUnit\Framework\TestCase;

/** Enum adalah SSOT aturan status; diuji tanpa database. */
final class EnumsTest extends TestCase
{
    // ── TaskStatus ──────────────────────────────────────────────────────────

    public function test_task_status_happy_path_transitions(): void
    {
        $this->assertTrue(TaskStatus::Draft->canTransitionTo(TaskStatus::Open));
        $this->assertTrue(TaskStatus::Open->canTransitionTo(TaskStatus::Dealt));
        $this->assertTrue(TaskStatus::Dealt->canTransitionTo(TaskStatus::Active));
        $this->assertTrue(TaskStatus::Active->canTransitionTo(TaskStatus::Submitted));
        $this->assertTrue(TaskStatus::Submitted->canTransitionTo(TaskStatus::Completed));
    }

    public function test_task_status_dispute_branch(): void
    {
        $this->assertTrue(TaskStatus::Submitted->canTransitionTo(TaskStatus::Disputed));
        $this->assertTrue(TaskStatus::Disputed->canTransitionTo(TaskStatus::Completed));
        $this->assertTrue(TaskStatus::Disputed->canTransitionTo(TaskStatus::Refunded));
    }

    public function test_task_status_cannot_go_backwards(): void
    {
        $this->assertFalse(TaskStatus::Completed->canTransitionTo(TaskStatus::Open));
        $this->assertFalse(TaskStatus::Active->canTransitionTo(TaskStatus::Draft));
        $this->assertFalse(TaskStatus::Dealt->canTransitionTo(TaskStatus::Open));
    }

    public function test_terminal_task_statuses_allow_nothing(): void
    {
        foreach ([TaskStatus::Completed, TaskStatus::Expired, TaskStatus::Cancelled, TaskStatus::Refunded] as $status) {
            $this->assertSame([], $status->allowedNext(), $status->value.' harus final');
            $this->assertTrue($status->isFinal());
        }
    }

    public function test_only_open_accepts_bids(): void
    {
        foreach (TaskStatus::cases() as $status) {
            $this->assertSame(
                $status === TaskStatus::Open,
                $status->acceptsBids(),
                $status->value,
            );
        }
    }

    public function test_cancellation_is_reachable_from_live_statuses(): void
    {
        foreach ([TaskStatus::Draft, TaskStatus::Open, TaskStatus::Dealt, TaskStatus::Active] as $status) {
            $this->assertTrue($status->canTransitionTo(TaskStatus::Cancelled), $status->value);
        }
    }

    // ── PaymentStatus ───────────────────────────────────────────────────────

    /** Held adalah satu-satunya gerbang pembuka activity. */
    public function test_only_held_opens_an_activity(): void
    {
        foreach (PaymentStatus::cases() as $status) {
            $this->assertSame(
                $status === PaymentStatus::Held,
                $status->opensActivity(),
                $status->value,
            );
        }
    }

    public function test_payment_transitions(): void
    {
        $this->assertTrue(PaymentStatus::Pending->canTransitionTo(PaymentStatus::AwaitingConfirmation));
        $this->assertTrue(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Cancelled));
        $this->assertTrue(PaymentStatus::AwaitingConfirmation->canTransitionTo(PaymentStatus::Held));
        // Ditolak pengelola → kembali ke pending, boleh dilaporkan ulang.
        $this->assertTrue(PaymentStatus::AwaitingConfirmation->canTransitionTo(PaymentStatus::Pending));
        $this->assertTrue(PaymentStatus::Held->canTransitionTo(PaymentStatus::Released));
        $this->assertTrue(PaymentStatus::Held->canTransitionTo(PaymentStatus::Refunded));
    }

    /**
     * INVARIAN: dana tidak bisa ditahan tanpa melewati antrean pengelola.
     *
     * Ini satu-satunya hal yang menahan "pemberi kerja menyatakan sendiri
     * uangnya sudah masuk". Kalau `pending -> held` dibuka lagi, endpoint
     * pemberi kerja bisa membuka pekerjaan tanpa ada yang memeriksa mutasi.
     */
    public function test_money_cannot_be_held_without_being_reported_first(): void
    {
        $this->assertFalse(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Held));

        $this->assertTrue(PaymentStatus::AwaitingConfirmation->awaitsConfirmation());
        $this->assertFalse(PaymentStatus::Pending->awaitsConfirmation());
        $this->assertFalse(PaymentStatus::Held->awaitsConfirmation());
    }

    public function test_payment_cannot_be_held_twice_or_reopened(): void
    {
        $this->assertFalse(PaymentStatus::Held->canTransitionTo(PaymentStatus::Held));
        $this->assertFalse(PaymentStatus::Released->canTransitionTo(PaymentStatus::Held));
        $this->assertFalse(PaymentStatus::Refunded->canTransitionTo(PaymentStatus::Held));
        $this->assertFalse(PaymentStatus::Cancelled->canTransitionTo(PaymentStatus::Held));
    }

    // ── ActivityStatus ──────────────────────────────────────────────────────

    public function test_activity_transitions(): void
    {
        $this->assertTrue(ActivityStatus::Open->canTransitionTo(ActivityStatus::InProgress));
        $this->assertTrue(ActivityStatus::InProgress->canTransitionTo(ActivityStatus::Submitted));
        $this->assertTrue(ActivityStatus::Submitted->canTransitionTo(ActivityStatus::Approved));
        $this->assertTrue(ActivityStatus::Submitted->canTransitionTo(ActivityStatus::Rejected));
        $this->assertTrue(ActivityStatus::Rejected->canTransitionTo(ActivityStatus::Submitted));
    }

    public function test_approved_activity_is_final(): void
    {
        foreach (ActivityStatus::cases() as $status) {
            $this->assertFalse(ActivityStatus::Approved->canTransitionTo($status), $status->value);
        }
    }

    public function test_activity_cannot_skip_starting(): void
    {
        $this->assertFalse(ActivityStatus::Open->canTransitionTo(ActivityStatus::Submitted));
        $this->assertFalse(ActivityStatus::Open->canTransitionTo(ActivityStatus::Approved));
    }

    // ── UserStatus ──────────────────────────────────────────────────────────

    public function test_only_active_users_can_transact_or_get_tokens(): void
    {
        foreach (UserStatus::cases() as $status) {
            $expected = $status === UserStatus::Active;
            $this->assertSame($expected, $status->canTransact(), $status->value);
            $this->assertSame($expected, $status->canReceiveTokens(), $status->value);
        }
    }

    public function test_pending_verification_is_detected(): void
    {
        $this->assertTrue(UserStatus::PendingVerification->isPendingVerification());
        $this->assertFalse(UserStatus::Active->isPendingVerification());
    }

    /** Default kolom harus keadaan paling tidak berhak. */
    public function test_pending_verification_is_the_first_case(): void
    {
        $this->assertSame(UserStatus::PendingVerification, UserStatus::cases()[0]);
    }

    // ── VerificationStatus ──────────────────────────────────────────────────

    public function test_only_verified_counts_as_verified(): void
    {
        foreach (VerificationStatus::cases() as $status) {
            $this->assertSame(
                $status === VerificationStatus::Verified,
                $status->isVerified(),
                $status->value,
            );
        }
    }

    public function test_final_verification_statuses(): void
    {
        $this->assertTrue(VerificationStatus::Verified->isFinal());
        $this->assertTrue(VerificationStatus::Rejected->isFinal());
        $this->assertTrue(VerificationStatus::Revoked->isFinal());
        $this->assertFalse(VerificationStatus::Pending->isFinal());
        $this->assertFalse(VerificationStatus::InReview->isFinal());
    }

    public function test_identity_requires_photos_bank_account_does_not(): void
    {
        $this->assertTrue(VerificationType::Identity->requiresPhotos());
        $this->assertFalse(VerificationType::BankAccount->requiresPhotos());
    }

    // ── ReviewerRole ────────────────────────────────────────────────────────

    /** Penilaian dari poster menaikkan agregat worker, dan sebaliknya. */
    public function test_reviewer_role_points_at_the_other_aggregate(): void
    {
        $this->assertSame('worker', ReviewerRole::Poster->affectedAggregate());
        $this->assertSame('poster', ReviewerRole::Worker->affectedAggregate());
    }

    // ── nilai enum sebagai kontrak ──────────────────────────────────────────

    public function test_enum_values_are_stable(): void
    {
        $this->assertSame(['hiring', 'working'], array_column(UserActiveMode::cases(), 'value'));
        $this->assertSame(
            ['token:access', 'token:refresh', 'admin:access', 'admin:refresh'],
            array_column(TokenAbility::cases(), 'value'),
        );
        $this->assertSame(['identity', 'bank_account'], array_column(VerificationType::cases(), 'value'));
        $this->assertSame(['poster', 'worker'], array_column(ReviewerRole::cases(), 'value'));
    }
}
