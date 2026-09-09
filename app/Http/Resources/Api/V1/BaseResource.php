<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Carbon\CarbonInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Pembantu bersama untuk semua transformer.
 *
 * Ada supaya format tanggal dan cara membentuk URL berkas publik hanya
 * ditentukan di SATU tempat. Sebelumnya `?->toIso8601String()` diulang
 * puluhan kali — kalau formatnya perlu berubah, harus disunting semuanya.
 */
abstract class BaseResource extends JsonResource
{
    /** Format waktu tunggal untuk seluruh API. */
    protected function iso(?CarbonInterface $at): ?string
    {
        return $at?->toIso8601String();
    }

    /**
     * URL berkas di disk PUBLIK. Hanya untuk berkas yang memang publik
     * seperti avatar — berkas verifikasi tidak pernah lewat sini.
     */
    protected function publicUrl(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }
}
