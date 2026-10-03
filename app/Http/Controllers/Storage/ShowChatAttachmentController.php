<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storage;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Menyajikan lampiran chat (`uploads/chat/{room}/{file}`) lewat Laravel —
 * alasan sama dengan ShowPublicUploadController: di hosting produksi symlink
 * `public/storage` tidak ada, jadi tanpa route ini setiap URL lampiran chat
 * (`reference` di ChatMessageResource) 404 walau berkasnya tersimpan.
 *
 * Dipisah dari ShowPublicUploadController karena bentuk path-nya beda (satu
 * segmen room lagi) dan jenis berkasnya bukan hanya gambar (video, audio,
 * dokumen). Pembatasan path ada di constraint route (routes/storage.php).
 */
final class ShowChatAttachmentController
{
    /** Nama berkas acak (`hashName`) dan tidak pernah ditimpa — aman di-cache setahun. */
    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function __construct(private readonly FilesystemFactory $filesystem) {}

    public function __invoke(string $room, string $file): Response
    {
        $disk = $this->filesystem->disk('public');
        $path = "uploads/chat/{$room}/{$file}";

        if (! $disk->exists($path)) {
            throw new NotFoundHttpException;
        }

        return $disk->response($path, headers: [
            'Cache-Control' => self::CACHE_CONTROL,
            // Berkas chat bisa berupa dokumen/teks: browser tidak boleh
            // menebak tipe dari isinya, dan apa pun yang terbuka di browser
            // berjalan dalam sandbox tanpa skrip.
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
