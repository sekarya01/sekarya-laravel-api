<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Enums\AdminAction;
use App\Enums\VerificationStatus;
use App\Http\Requests\Api\V1\Admin\ReviewVerificationRequest;
use Illuminate\Http\Request;

/**
 * Keputusan pengelola atas satu pengajuan verifikasi.
 *
 * Satu DTO untuk tiga keputusan, dibangun lewat tiga konstruktor bernama —
 * bukan satu `fromRequest()` yang membaca keputusannya dari payload. Kalau
 * keputusannya datang dari payload, endpoint `/approve` bisa dipakai untuk
 * menolak, dan izin per-endpoint kehilangan artinya.
 *
 * `decision` dan `action` dibawa berdampingan, tidak diturunkan satu dari
 * yang lain: menurunkannya menuntut `match` atas sepuluh nilai AdminAction
 * yang sembilan di antaranya tidak berlaku di sini, dan cabang yang tidak
 * berlaku itu hanya bisa berakhir sebagai galat runtime.
 */
final readonly class ReviewVerificationData
{
    private function __construct(
        public VerificationStatus $decision,
        public AdminAction $action,
        public ?string $reason,
        public ?string $ip,
    ) {}

    public static function approve(Request $request): self
    {
        return new self(
            VerificationStatus::Verified,
            AdminAction::VerificationApproved,
            null,
            $request->ip(),
        );
    }

    public static function reject(ReviewVerificationRequest $request): self
    {
        return new self(
            VerificationStatus::Rejected,
            AdminAction::VerificationRejected,
            $request->string('reason')->value(),
            $request->ip(),
        );
    }

    public static function revoke(ReviewVerificationRequest $request): self
    {
        return new self(
            VerificationStatus::Revoked,
            AdminAction::VerificationRevoked,
            $request->string('reason')->value(),
            $request->ip(),
        );
    }
}
