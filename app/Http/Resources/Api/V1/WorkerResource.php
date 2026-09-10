<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\UserWorker;
use Illuminate\Http\Request;

/**
 * Satu baris di daftar pekerja.
 *
 * Sengaja TIDAK punya bentuknya sendiri: ia meneruskan ke
 * `PublicUserResource`, yang sudah memuat batas pengungkapan untuk "orang lain
 * melihat seseorang" — umur tanpa tanggal lahir, kota kerja tanpa jalan dan
 * tanpa koordinat.
 *
 * Kalau daftar ini menyusun bentuknya sendiri, batas itu jadi ada di DUA
 * tempat, dan yang kedua tidak dijaga test yang sama. Field baru yang
 * ditambahkan ke salah satunya akan bocor lewat yang lain tanpa ada yang
 * memberi tahu — dan arah bocornya justru ke endpoint yang paling banyak
 * mengembalikan orang sekaligus.
 *
 * Yang berbeda hanya SUMBERNYA: paginator-nya berisi `UserWorker` supaya
 * cursor dihitung dari kolom yang benar-benar diurutkan.
 *
 * @mixin UserWorker
 */
final class WorkerResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return PublicUserResource::make($this->resource->user)->toArray($request);
    }
}
