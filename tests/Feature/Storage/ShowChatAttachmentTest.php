<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lampiran chat dilayani aplikasi di `/storage/uploads/chat/{room}/{file}` —
 * URL `reference` yang dikirim API — walau symlink `public/storage` tidak ada
 * (kondisi hosting produksi; sebelum route ini semua lampiran chat 404).
 */
final class ShowChatAttachmentTest extends TestCase
{
    private const ROOM = '01M3RB697F7K53HFWY7MCXD5C9';

    private const FILE = 'AGqtPdTwg9QXTOnbwJS1XyVj5oPSG9mM92LEziX8';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_serves_chat_photo_video_audio_and_document(): void
    {
        foreach (['jpg' => 'foto', 'mp4' => 'video', 'mpga' => 'audio', 'pdf' => 'dokumen'] as $ext => $body) {
            $path = 'uploads/chat/'.self::ROOM.'/'.self::FILE.'.'.$ext;
            Storage::disk('public')->put($path, $body);

            $response = $this->get('/storage/'.$path)->assertOk();

            $this->assertSame($body, $response->streamedContent());
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        }
    }

    public function test_url_matches_how_the_attachment_action_stores_files(): void
    {
        // Sama seperti StoreChatAttachmentAction: store() di folder room.
        $path = UploadedFile::fake()->create('laporan.pdf', 4, 'application/pdf')
            ->store('uploads/chat/'.self::ROOM, 'public');

        $this->get('/storage/'.$path)->assertOk();
    }

    public function test_missing_file_is_404(): void
    {
        $this->get('/storage/uploads/chat/'.self::ROOM.'/'.self::FILE.'.jpg')->assertNotFound();
    }

    public function test_paths_outside_a_room_folder_are_not_served(): void
    {
        Storage::disk('public')->put('uploads/chat/bukan-ulid/'.self::FILE.'.jpg', 'x');
        Storage::disk('public')->put('uploads/chat/'.self::FILE.'.jpg', 'x');

        $this->assertNotServed('/storage/uploads/chat/bukan-ulid/'.self::FILE.'.jpg');
        $this->assertNotServed('/storage/uploads/chat/'.self::FILE.'.jpg');
        $this->assertNotServed('/storage/uploads/chat/'.self::ROOM.'/..%2F..%2Fsecret.jpg');
        $this->assertNotServed('/storage/uploads/chat/'.self::ROOM.'/a.b.jpg');
    }

    private function assertNotServed(string $url): void
    {
        $status = $this->get($url)->getStatusCode();
        $this->assertContains($status, [403, 404], "{$url} seharusnya tidak dilayani, dapat {$status}");
    }
}
