<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tag pujian yang bisa dipilih saat memberi rating (chip di dialog rating).
 *
 * Himpunannya DIBEDAKAN PER ARAH penilaian, karena yang dinilai berbeda:
 * pemberi kerja menilai CARA BEKERJA seseorang, pekerja menilai CARA
 * MEMPEKERJAKAN. "Pembayaran tepat waktu" tidak berarti apa pun untuk
 * pekerja, dan "kerja rapi" tidak berarti apa pun untuk pemberi kerja.
 *
 * | Arah (`reviewer_role`)       | Tag                                      |
 * |------------------------------|------------------------------------------|
 * | poster → pekerja (`poster`)  | on_time, tidy, friendly, skilled         |
 * | pekerja → poster (`worker`)  | clear_brief, friendly, on_time_payment   |
 *
 * `forRole()` adalah SSOT-nya — dibaca aturan validasi CreateReviewRequest
 * DAN penjaga di CreateReviewAction, jadi menambah tag hanya di satu tempat
 * tidak bisa membuat keduanya berbeda pendapat.
 *
 * Nilainya kode mesin berbahasa Inggris, bukan label: label ("Tepat Waktu",
 * "Kerja Rapi") urusan klien, supaya mengganti kata di layar tidak mengubah
 * data yang sudah tersimpan. Tag hanya DITAMPILKAN, tidak pernah disaring —
 * itu sebabnya ia boleh tinggal di kolom JSON.
 */
enum ReviewTag: string
{
    case OnTime = 'on_time';
    case Tidy = 'tidy';
    case Friendly = 'friendly';
    case Skilled = 'skilled';
    case ClearBrief = 'clear_brief';
    case OnTimePayment = 'on_time_payment';

    /** Batas tag per penilaian. */
    public const int MAX_PER_REVIEW = 5;

    /**
     * Tag yang boleh dipakai penilai dengan peran ini.
     *
     * @return list<self>
     */
    public static function forRole(ReviewerRole $role): array
    {
        return match ($role) {
            ReviewerRole::Poster => [self::OnTime, self::Tidy, self::Friendly, self::Skilled],
            ReviewerRole::Worker => [self::ClearBrief, self::Friendly, self::OnTimePayment],
        };
    }

    /** @return list<string> */
    public static function valuesForRole(ReviewerRole $role): array
    {
        return array_map(static fn (self $tag): string => $tag->value, self::forRole($role));
    }
}
