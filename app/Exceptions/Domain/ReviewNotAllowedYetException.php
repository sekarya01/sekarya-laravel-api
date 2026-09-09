<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class ReviewNotAllowedYetException extends DomainException
{
    public static function taskNotCompleted(): self
    {
        return new self('Penilaian hanya bisa diberikan setelah task selesai.');
    }

    public static function alreadyReviewed(): self
    {
        return new self('Anda sudah memberi penilaian untuk task ini.');
    }

    public function errorCode(): string
    {
        return 'review_not_allowed';
    }
}
