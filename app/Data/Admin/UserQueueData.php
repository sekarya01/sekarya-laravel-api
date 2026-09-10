<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Data\CursorPageData;
use App\Enums\UserStatus;
use App\Http\Requests\Api\V1\Admin\UserQueueRequest;

/**
 * Penyaring daftar pengguna untuk moderasi.
 *
 * `email` adalah pencocokan PERSIS, bukan pencarian sebagian. Proyek ini
 * tidak memakai `LIKE` di mana pun (`%x%` tidak bisa memakai indeks, jadi
 * setiap pencarian sebagian berarti memindai seluruh tabel pengguna), dan
 * pencarian nama sebagian butuh tabel indeks terbalik tersendiri dengan
 * normalisasi dua sisi — pola `task_search`. Sampai tabel itu ada, yang
 * disediakan adalah pencarian yang benar-benar terindeks: alamat email
 * (kolom unique) dan ULID lewat endpoint detail.
 */
final readonly class UserQueueData
{
    public function __construct(
        public CursorPageData $page,
        public ?UserStatus $status = null,
        public ?string $email = null,
    ) {}

    public static function fromRequest(UserQueueRequest $request): self
    {
        return new self(
            page: $request->page(),
            status: $request->filled('status')
                ? UserStatus::from($request->string('status')->value())
                : null,
            email: $request->filled('email')
                ? mb_strtolower(trim($request->string('email')->value()))
                : null,
        );
    }
}
