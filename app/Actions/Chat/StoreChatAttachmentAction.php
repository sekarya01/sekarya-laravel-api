<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Data\Chat\StoreChatAttachmentData;
use App\Enums\ChatMessageType;
use App\Exceptions\Domain\ChatAttachmentTooLargeException;
use App\Models\ChatAttachment;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\Chat\ChatAccess;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use RuntimeException;

/**
 * Unggah satu lampiran chat. Langkah pertama dari dua: hasilnya (`id`)
 * dikirim sebagai `attachment_id` di pesan.
 *
 * Jenis (image/video/audio/file) diturunkan dari MIME berkas yang
 * sungguh diterima, bukan dari klaim klien. Nama berkas di disk acak
 * (hashName); nama asli hanya disimpan untuk DITAMPILKAN.
 */
final class StoreChatAttachmentAction
{
    public function __construct(
        private readonly ChatAccess $access,
        private readonly Filesystem $storage,
        private readonly Config $config,
    ) {}

    public function handle(ChatRoom $room, StoreChatAttachmentData $data, User $user): ChatAttachment
    {
        $this->access->writer($room, $user);

        $file = $data->file;
        $mime = (string) ($file->getMimeType() ?? 'application/octet-stream');
        $kind = ChatMessageType::forFile($mime, (string) $file->getClientOriginalExtension());
        $this->assertWithinLimits($kind, (int) ceil($file->getSize() / 1024), $data->duration);

        [$width, $height] = $kind === ChatMessageType::Image
            ? $this->imageSize($file->getRealPath(), $data)
            : [$data->width, $data->height];

        $path = $file->store('uploads/chat/'.$room->ulid, 'public');
        if (! is_string($path) || $path === '') {
            // Disk yang gagal menulis tidak boleh berakhir 201 dengan path kosong.
            throw new RuntimeException('Gagal menyimpan lampiran chat.');
        }

        return ChatAttachment::query()->create([
            'room_id' => $room->getKey(),
            'uploader_id' => $user->getKey(),
            'kind' => $kind,
            'path' => $path,
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'extension' => strtolower((string) $file->getClientOriginalExtension()),
            'mime_type' => mb_substr($mime, 0, 100),
            'size' => (int) $file->getSize(),
            'duration' => in_array($kind, [ChatMessageType::Video, ChatMessageType::Audio], true) ? $data->duration : 0,
            'width' => in_array($kind, [ChatMessageType::Image, ChatMessageType::Video], true) ? $width : 0,
            'height' => in_array($kind, [ChatMessageType::Image, ChatMessageType::Video], true) ? $height : 0,
            'waveform' => $kind === ChatMessageType::Audio ? $data->waveform : null,
        ]);
    }

    private function assertWithinLimits(ChatMessageType $kind, int $sizeKb, int $seconds): void
    {
        $limits = (array) $this->config->get('sekarya.chat.limits.'.$kind->value);
        $maxKb = (int) ($limits['max_kb'] ?? 0);
        $maxSeconds = (int) ($limits['max_seconds'] ?? 0);

        if ($sizeKb > $maxKb || ($maxSeconds > 0 && $seconds > $maxSeconds)) {
            throw ChatAttachmentTooLargeException::for($kind->value, ['max_kb' => $maxKb, 'max_seconds' => $maxSeconds]);
        }
    }

    /** @return array{0: int, 1: int} */
    private function imageSize(string|false $path, StoreChatAttachmentData $data): array
    {
        $size = $path === false ? false : @getimagesize($path);

        return $size === false ? [$data->width, $data->height] : [(int) $size[0], (int) $size[1]];
    }
}
