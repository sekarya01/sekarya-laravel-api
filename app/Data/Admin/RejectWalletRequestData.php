<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Http\Requests\Api\V1\Admin\RejectWalletRequestRequest;

/**
 * Penolakan permintaan saldo. Alasannya WAJIB dan bukan untuk jejak audit
 * saja: penggunanya yang membacanya, dan "ditolak" tanpa keterangan hanya
 * menghasilkan permintaan berikutnya yang sama persis.
 */
final readonly class RejectWalletRequestData
{
    public function __construct(
        public string $reason,
        public ?string $ip = null,
    ) {}

    public static function fromRequest(RejectWalletRequestRequest $request): self
    {
        return new self(
            reason: trim($request->string('reason')->value()),
            ip: $request->ip(),
        );
    }
}
