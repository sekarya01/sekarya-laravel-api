<?php

declare(strict_types=1);

namespace App\Support\Push;

/**
 * Sisipkan data UTUH (bentuk resource API) ke `data` push FCM supaya klien
 * langsung memasangnya ke state/notifikasi tanpa memanggil API — dan bebas
 * dipakai untuk kustomisasi notifikasi kelak.
 *
 * Batas `data` FCM 4 KB. Urutan percobaan:
 *  1. `<key>`    = JSON polos (paling mudah dibaca/di-debug);
 *  2. `<key>_gz` = JSON di-gzip lalu base64 (deskripsi panjang, aktivitas);
 *  3. tidak muat juga → TIDAK disisipkan; klien kembali memuat dari API.
 */
final class PushSnapshot
{
    /** Anggaran byte seluruh `data` (batas FCM 4096, sisakan ruang amplop). */
    public const BUDGET = 3800;

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $value
     * @return array<string, string>
     */
    public static function attach(array $data, string $key, array $value): array
    {
        $json = json_encode($value, self::JSON_FLAGS);
        if ($json === false) {
            return $data;
        }

        $plain = [...$data, $key => $json];
        if (self::fits($plain)) {
            return $plain;
        }

        $gzipped = gzencode($json, 9);
        if ($gzipped === false) {
            return $data;
        }

        $compressed = [...$data, $key.'_gz' => base64_encode($gzipped)];

        return self::fits($compressed) ? $compressed : $data;
    }

    /** @param  array<string, string>  $data */
    private static function fits(array $data): bool
    {
        return strlen((string) json_encode($data, self::JSON_FLAGS)) <= self::BUDGET;
    }
}
