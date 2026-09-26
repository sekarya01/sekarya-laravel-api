<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;

/**
 * Dokumen identitas (KTP/selfie) — G2a: di mana disimpan, dan milik siapa.
 *
 * Dokumen identitas TIDAK boleh publik, jadi ia tidak lewat `POST uploads`
 * (disk `public`) melainkan `POST me/verifications/documents` dengan disk
 * `private_docs` yang berada di luar `public/`. Path-nya hanya dibaca lewat
 * endpoint ber-auth admin (G2b), bukan URL.
 *
 * Kepemilikan ditandai di nama berkas dengan HMAC atas id pengguna dan
 * `APP_KEY` — sama polanya dengan `ProofPhotos`: tidak bisa dipalsukan,
 * tidak membuka identitas, dan dicocokkan tanpa kueri. Penyerahan verifikasi
 * menolak path yang bukan milik pengaju, sehingga satu akun tidak bisa
 * melampirkan dokumen milik akun lain.
 */
final class VerificationDocuments
{
    public const string FOLDER = 'verifications';

    public const string DISK = 'private_docs';

    private const int TAG_LENGTH = 16;

    public function __construct(
        private readonly Config $config,
        private readonly FilesystemFactory $filesystem,
    ) {}

    /**
     * Simpan satu dokumen atas nama pengguna. Disk `private_docs` ber-`throw =>
     * true`, jadi penulisan yang gagal menjadi exception (500 tercatat),
     * bukan `false` yang berubah jadi path kosong.
     */
    public function store(UploadedFile $file, User $owner): string
    {
        return (string) $file->storeAs(
            self::FOLDER,
            $this->ownerTag($owner).$file->hashName(),
            self::DISK,
        );
    }

    /**
     * Path ini dokumen yang diunggah PENGGUNA INI, dan berkasnya ada.
     *
     * Tiga syarat, semuanya wajib: bentuk path persis seperti yang ditulis
     * `store()`, tanda pemilik cocok, dan berkasnya ada di disk privat.
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

        return $this->filesystem->disk(self::DISK)->exists($path);
    }

    private function ownerTag(User $owner): string
    {
        return substr(
            hash_hmac('sha256', 'verification-document:'.$owner->getKey(), (string) $this->config->get('app.key')),
            0,
            self::TAG_LENGTH,
        );
    }
}
