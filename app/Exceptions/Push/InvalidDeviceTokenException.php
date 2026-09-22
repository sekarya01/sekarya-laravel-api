<?php

declare(strict_types=1);

namespace App\Exceptions\Push;

/**
 * FCM menyatakan token perangkat tidak lagi sah.
 *
 * Perlu kelas sendiri karena pemanggilnya bertindak berbeda: token seperti ini
 * DISAPU dari basis data, sedangkan kegagalan lain hanya dicatat dan dicoba
 * lagi lain waktu. Tanpa pemisahan ini, token mati akan dicoba selamanya.
 *
 * Token-nya sendiri TIDAK pernah dibawa ke pesan galat maupun log.
 */
final class InvalidDeviceTokenException extends FcmException
{
    public static function make(): self
    {
        return new self('Token perangkat tidak lagi terdaftar di FCM.');
    }
}
