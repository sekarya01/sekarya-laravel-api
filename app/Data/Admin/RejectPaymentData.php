<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Http\Requests\Api\V1\Admin\RejectPaymentRequest;

/**
 * Penolakan laporan transfer. Alasannya WAJIB dan bukan untuk jejak audit
 * saja — pemberi kerja yang membaca "ditolak" tanpa keterangan tidak punya
 * cara tahu apa yang harus diperbaiki, dan akan melaporkan hal yang sama lagi.
 */
final readonly class RejectPaymentData
{
    public function __construct(
        public string $reason,
        public ?string $ip = null,
    ) {}

    public static function fromRequest(RejectPaymentRequest $request): self
    {
        return new self(
            reason: trim($request->string('reason')->value()),
            ip: $request->ip(),
        );
    }
}
