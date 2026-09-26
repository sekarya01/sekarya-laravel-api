<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\ReviewerRole;
use App\Enums\ReviewTag;

/**
 * Tag milik arah penilaian yang lain ("pembayaran tepat waktu" dari pemberi
 * kerja untuk pekerjanya).
 *
 * Lewat HTTP, CreateReviewRequest sudah menolaknya lebih dulu sebagai
 * `errors.tags.N`. Penjaga ini untuk pemanggil di luar jalur itu (job,
 * command, test) — aturan bisnis tetap tinggal di Action.
 */
final class ReviewTagNotAllowedException extends DomainException
{
    /** @param list<ReviewTag> $rejected */
    public function __construct(private readonly array $rejected, private readonly ReviewerRole $role)
    {
        parent::__construct('Tag ini tidak berlaku untuk penilaian dari arah ini.');
    }

    public function errorCode(): string
    {
        return 'review_tag_not_allowed';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'tags' => array_map(static fn (ReviewTag $t): string => $t->value, $this->rejected),
            'allowed' => ReviewTag::valuesForRole($this->role),
        ];
    }
}
