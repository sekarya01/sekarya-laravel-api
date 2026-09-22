<?php

declare(strict_types=1);

namespace App\Exceptions\Push;

use RuntimeException;

/**
 * Kegagalan transport push — bukan pelanggaran aturan bisnis.
 *
 * Karena itu ia TIDAK turunan DomainException: DomainException dipetakan
 * bootstrap/app.php menjadi respons HTTP untuk klien, sedangkan kegagalan
 * push terjadi di latar (job antrean) dan tidak pernah menjadi jawaban
 * sebuah request. Menaruhnya di DomainException akan membuat docs-contract
 * menuntut kode galatnya muncul di openapi.yaml — padahal klien tidak pernah
 * menerimanya.
 *
 * Tidak `final`: InvalidDeviceTokenException memperluasnya sebagai kasus
 * khusus yang perlu diperlakukan berbeda (token disapu, bukan sekadar dicatat).
 */
class FcmException extends RuntimeException
{
    public static function misconfigured(string $reason): self
    {
        return new self('FCM belum dikonfigurasi: '.$reason);
    }

    public static function authenticationFailed(int $status, string $body): self
    {
        return new self(sprintf(
            'FCM gagal mengambil access token (HTTP %d): %s',
            $status,
            mb_substr($body, 0, 300),
        ));
    }

    public static function transportFailed(int $status, string $body): self
    {
        return new self(sprintf(
            'FCM menolak pengiriman (HTTP %d): %s',
            $status,
            mb_substr($body, 0, 300),
        ));
    }
}
