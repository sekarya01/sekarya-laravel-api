<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storage;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Menyajikan unggahan publik (foto task, avatar) lewat Laravel sendiri.
 *
 * Biasanya berkas ini dilayani langsung oleh web server lewat symlink
 * `public/storage -> storage/app/public`. Di hosting produksi symlink itu
 * tidak bisa dijaga: tidak ada terminal, `exec()` dimatikan, dan target yang
 * dibuat skrip deploy terbukti berbeda dari `storage_path()` milik aplikasi
 * yang benar-benar melayani web. Hasilnya unggahan tersimpan, tapi setiap
 * URL-nya 404.
 *
 * Route ini menutup celah tersebut. Yang MEMBACA berkas adalah aplikasi yang
 * sama dengan yang MENULISNYA, sehingga lokasinya selalu cocok di mana pun
 * `storage_path()` berada. Bila symlink kebetulan sehat, web server tetap
 * melayani berkas lebih dulu (`.htaccess` hanya meneruskan path yang bukan
 * berkas nyata) dan route ini tidak pernah tersentuh.
 *
 * Tidak ada Action: tidak ada aturan bisnis di sini, hanya membaca berkas.
 * Pembatasan path ada di constraint route, bukan di kode ini.
 */
final class ShowPublicUploadController
{
    /**
     * Nama berkas unggahan selalu acak (`hashName`) dan tidak pernah ditimpa,
     * jadi isi di balik satu URL tidak akan berubah — aman di-cache setahun.
     */
    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function __construct(private readonly FilesystemFactory $filesystem) {}

    public function __invoke(string $folder, string $file): Response
    {
        $disk = $this->filesystem->disk('public');
        $path = "uploads/{$folder}/{$file}";

        if (! $disk->exists($path)) {
            throw new NotFoundHttpException;
        }

        return $disk->response($path, headers: [
            'Cache-Control' => self::CACHE_CONTROL,
            // Browser tidak boleh menebak tipe dari isi berkas: berkas yang
            // lolos validasi sebagai gambar tetap harus diperlakukan sebagai
            // gambar, bukan dieksekusi sebagai HTML/skrip.
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
