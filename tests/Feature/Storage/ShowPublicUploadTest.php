<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Unggahan publik dilayani aplikasi di URL `/storage/uploads/...` — URL yang
 * sama dengan yang dibangun mobile dari `path`, sehingga foto tampil walau
 * symlink `public/storage` tidak ada atau menunjuk ke folder yang salah.
 */
final class ShowPublicUploadTest extends TestCase
{
    private const FILE = 'aB3dE5fG7hJ9kL1mN3pQ5rS7tU9vW1xY3zA5bC7d.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_serves_an_uploaded_task_photo_with_long_cache(): void
    {
        $bytes = $this->storeImage('uploads/tasks/'.self::FILE);

        $response = $this->get('/storage/uploads/tasks/'.self::FILE)->assertOk();

        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame($bytes, $response->streamedContent());

        $cache = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('max-age=31536000', $cache);
        $this->assertStringContainsString('immutable', $cache);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_serves_avatars_too(): void
    {
        $this->storeImage('uploads/avatars/'.self::FILE);

        $this->get('/storage/uploads/avatars/'.self::FILE)->assertOk();
    }

    public function test_url_matches_what_the_upload_endpoint_returns(): void
    {
        // Jalur nyata: berkas disimpan persis seperti StoreUploadController
        // menyimpannya, lalu diambil lewat path yang dikembalikan ke mobile.
        $path = UploadedFile::fake()->image('foto.jpg', 64, 64)->store('uploads/tasks', 'public');

        $this->get('/storage/'.$path)->assertOk();
    }

    public function test_response_sets_no_session_cookie(): void
    {
        $this->storeImage('uploads/tasks/'.self::FILE);

        $response = $this->get('/storage/uploads/tasks/'.self::FILE)->assertOk();

        // Cookie sesi membuat cache HTTP menolak menyimpan gambar.
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_missing_file_is_404(): void
    {
        $this->get('/storage/uploads/tasks/'.self::FILE)->assertNotFound();
    }

    public function test_folders_outside_the_public_uploads_are_not_served(): void
    {
        Storage::disk('public')->put('uploads/private/'.self::FILE, 'rahasia');
        Storage::disk('public')->put('lain/'.self::FILE, 'rahasia');

        $this->assertNotServed('/storage/uploads/private/'.self::FILE);
        $this->assertNotServed('/storage/lain/'.self::FILE);
    }

    public function test_path_traversal_is_rejected(): void
    {
        foreach ([
            '/storage/uploads/tasks/../../../.env',
            '/storage/uploads/tasks/..%2F..%2F..%2F.env',
            '/storage/uploads/tasks/%2e%2e/%2e%2e/.env',
            '/storage/uploads/tasks/foto.php',
            '/storage/uploads/tasks/foto.jpg.php',
            '/storage/uploads/tasks/foto.svg',
        ] as $uri) {
            $this->assertNotServed($uri);
        }
    }

    /** Kembalikan isi berkas yang disimpan, untuk dibandingkan dengan respons. */
    private function storeImage(string $path): string
    {
        $bytes = UploadedFile::fake()->image('foto.jpg', 32, 32)->getContent();
        Storage::disk('public')->put($path, $bytes);

        return $bytes;
    }

    /**
     * Tidak dilayani = bukan 200. Route `storage.local` bawaan Laravel yang
     * menangkap sisanya membalas 403 di luar produksi dan 404 di produksi.
     */
    private function assertNotServed(string $uri): void
    {
        $status = $this->get($uri)->getStatusCode();

        $this->assertContains($status, [403, 404], "{$uri} dilayani dengan status {$status}");
    }
}
