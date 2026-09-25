<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;

/**
 * Foto bukti hasil kerja (U11): di mana disimpan, dan milik siapa.
 *
 * Masalah yang diselesaikan: `POST activities/{a}/submit` menerima PATH,
 * bukan berkas. Tanpa pemeriksaan kepemilikan, pekerja bisa menyerahkan path
 * foto bukti milik orang lain (URL-nya publik — ia terlihat di Detail Tugas)
 * sebagai buktinya sendiri, dan pemberi kerja tidak punya cara tahu.
 *
 * Tanpa tabel baru: pemiliknya DITANDAI DI NAMA BERKAS. Nama berkas bukti =
 * 16 karakter tanda pemilik + nama acak 40 karakter (`hashName`). Tanda
 * pemilik adalah HMAC atas id pengguna dengan `APP_KEY`, jadi:
 *
 *  - tidak bisa dipalsukan — menghitungnya butuh kunci aplikasi;
 *  - tidak membuka identitas — URL tidak memuat id maupun ULID orangnya;
 *  - dicocokkan tanpa kueri — cukup hitung ulang untuk pengguna yang sedang
 *    menyerahkan.
 *
 * Harganya: mengganti `APP_KEY` membuat foto yang SUDAH diunggah tapi BELUM
 * diserahkan ditolak (unggah ulang). Foto yang sudah tersimpan di
 * `activities.proof_photos` tidak terpengaruh — pemeriksaan hanya berjalan
 * saat penyerahan.
 *
 * Bentuk nama berkas tetap lolos constraint route penyaji unggahan
 * (`[A-Za-z0-9]{1,100}` + ekstensi), jadi URL-nya dilayani seperti foto task.
 */
final class ProofPhotos
{
    public const string FOLDER = 'uploads/proofs';

    private const int TAG_LENGTH = 16;

    public function __construct(
        private readonly Config $config,
        private readonly FilesystemFactory $filesystem,
    ) {}

    /**
     * Simpan satu foto bukti atas nama pengguna. Disk `public` ber-`throw =>
     * true`, jadi penulisan yang gagal menjadi exception (500 tercatat),
     * bukan `false` yang berubah jadi path kosong.
     */
    public function store(UploadedFile $file, User $owner): string
    {
        return (string) $file->storeAs(
            self::FOLDER,
            $this->ownerTag($owner).$file->hashName(),
            'public',
        );
    }

    /**
     * Path ini foto bukti yang diunggah PENGGUNA INI, dan berkasnya ada.
     *
     * Tiga syarat, semuanya wajib: bentuk path persis seperti yang ditulis
     * `store()` (folder bukti, tanpa `..`), tanda pemilik cocok, dan berkasnya
     * benar-benar ada di disk — path yang dikarang klien dengan tanda yang
     * kebetulan benar tetap bukan bukti.
     */
    public function isOwnedBy(string $path, User $owner): bool
    {
        $pattern = sprintf(
            '#\A%s/([a-f0-9]{%d})[A-Za-z0-9]{40}\.(jpe?g|png|webp)\z#',
            preg_quote(self::FOLDER, '#'),
            self::TAG_LENGTH,
        );

        if (preg_match($pattern, $path, $m) !== 1) {
            return false;
        }

        if (! hash_equals($this->ownerTag($owner), $m[1])) {
            return false;
        }

        return $this->filesystem->disk('public')->exists($path);
    }

    /** Batas bawah jumlah foto, dari config (0 = opsional). */
    public function minimum(): int
    {
        return max(0, (int) $this->config->get('sekarya.activities.min_proof_photos', 1));
    }

    private function ownerTag(User $owner): string
    {
        return substr(
            hash_hmac('sha256', 'proof-photo:'.$owner->getKey(), (string) $this->config->get('app.key')),
            0,
            self::TAG_LENGTH,
        );
    }
}
