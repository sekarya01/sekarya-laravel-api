<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Data\CursorPageData;
use App\Enums\PaymentStatus;
use App\Http\Requests\Api\V1\Admin\PaymentQueueRequest;

/**
 * Penyaring antrean pembayaran.
 *
 * Bawaannya `awaiting_confirmation` — satu-satunya status yang menunggu
 * tindakan manusia. Sama alasannya seperti antrean verifikasi.
 */
final readonly class PaymentQueueData
{
    public function __construct(
        public CursorPageData $page,
        public ?PaymentStatus $status = null,
    ) {}

    public static function fromRequest(PaymentQueueRequest $request): self
    {
        return new self(
            page: $request->page(),
            status: $request->filled('status')
                ? PaymentStatus::from($request->string('status')->value())
                : null,
        );
    }
}
