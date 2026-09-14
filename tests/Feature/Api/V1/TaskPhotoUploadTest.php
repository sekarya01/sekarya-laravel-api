<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Unggah gambar untuk foto task — lewat HTTP, termasuk alur ujung-ke-ujung
 * mobile: unggah tiap foto, pakai `path` yang dikembalikan di `photos[]`.
 */
final class TaskPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
    }

    public function test_upload_returns_path_and_url_and_stores_the_file(): void
    {
        Storage::fake('public');

        $path = $this->asUser($this->poster)
            ->postJson(route('v1.uploads.store'), [
                'file' => UploadedFile::fake()->image('foto.jpg', 1200, 900)->size(500),
                'purpose' => 'task',
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['path', 'url']])
            ->json('data.path');

        $this->assertStringStartsWith('uploads/tasks/', $path);
        Storage::disk('public')->assertExists($path);
    }

    /**
     * Penulisan yang gagal HARUS terlihat. Sebelumnya disk `public` memakai
     * `throw => false`: `store()` mengembalikan false, `(string) false`
     * menjadi "", dan endpoint membalas 201 dengan `path` kosong. Mobile lalu
     * membuat task seolah fotonya tersimpan, padahal tidak ada berkas apa pun.
     *
     * Kegagalan disimulasikan dengan folder tujuan yang SUDAH ADA tapi baca-saja,
     * memakai konfigurasi disk `public` yang ASLI — hanya root-nya yang
     * diganti — supaya yang diuji benar-benar setelan `throw` milik aplikasi.
     *
     * Foldernya harus sudah ada: kalau yang gagal pembuatan folder, Flysystem
     * melempar UnableToCreateDirectory tanpa peduli `throw`, dan test ini
     * lulus walau bug-nya masih ada. Yang senyap hanya penulisan BERKAS.
     */
    public function test_upload_fails_loudly_when_the_file_cannot_be_written(): void
    {
        $root = sys_get_temp_dir().'/sekarya-readonly-'.bin2hex(random_bytes(4));
        mkdir($root.'/uploads/tasks', 0755, true);
        chmod($root.'/uploads/tasks', 0555);

        try {
            Storage::set('public', Storage::build([
                ...config('filesystems.disks.public'),
                'root' => $root,
            ]));

            $response = $this->asUser($this->poster)
                ->postJson(route('v1.uploads.store'), [
                    'file' => UploadedFile::fake()->image('foto.jpg')->size(300),
                ]);

            $response->assertStatus(500);
            $this->assertNull($response->json('data.path'), 'path tidak boleh dikembalikan');
            $this->assertSame([], glob($root.'/uploads/tasks/*') ?: [], 'tidak boleh ada berkas tertulis');
        } finally {
            chmod($root.'/uploads/tasks', 0755);
            rmdir($root.'/uploads/tasks');
            rmdir($root.'/uploads');
            rmdir($root);
        }
    }

    public function test_uploaded_path_can_be_used_as_a_task_photo(): void
    {
        Storage::fake('public');

        $path = $this->asUser($this->poster)
            ->postJson(route('v1.uploads.store'), [
                'file' => UploadedFile::fake()->image('foto.jpg')->size(300),
            ])
            ->assertCreated()
            ->json('data.path');

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), [
                'category_id' => $this->anyCategory()->getKey(),
                'title' => 'Bersih rumah dengan foto',
                'description' => 'Ada foto kondisi awal.',
                'budget_min' => 150_000,
                'city' => 'Jakarta',
                'needed_at' => now()->addDays(3)->toIso8601String(),
                'photos' => [$path],
                'publish_now' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.photos.0', $path);
    }

    public function test_non_image_is_rejected(): void
    {
        Storage::fake('public');

        $this->asUser($this->poster)
            ->postJson(route('v1.uploads.store'), [
                'file' => UploadedFile::fake()->create('dokumen.txt', 100, 'text/plain'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_oversized_image_is_rejected(): void
    {
        Storage::fake('public');

        $this->asUser($this->poster)
            ->postJson(route('v1.uploads.store'), [
                'file' => UploadedFile::fake()->image('besar.jpg')->size(11_000),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_unknown_purpose_is_rejected(): void
    {
        Storage::fake('public');

        $this->asUser($this->poster)
            ->postJson(route('v1.uploads.store'), [
                'file' => UploadedFile::fake()->image('foto.jpg')->size(300),
                'purpose' => 'ktp',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purpose']);
    }

    public function test_guest_cannot_upload(): void
    {
        Storage::fake('public');

        $this->postJson(route('v1.uploads.store'), [
            'file' => UploadedFile::fake()->image('foto.jpg')->size(300),
        ])->assertUnauthorized();
    }
}
