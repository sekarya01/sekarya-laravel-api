<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\ChatMessageType;
use App\Models\ChatAttachment;
use Illuminate\Http\Request;

/**
 * Hasil unggahan lampiran chat. `id` dikirim sebagai `attachment_id` saat
 * mengirim pesan; `type` = jenis pesan yang harus dipakai.
 *
 * @mixin ChatAttachment
 */
final class ChatAttachmentResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->kind->value,
            'reference' => $this->publicUrl($this->path),
            'file_name' => $this->file_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'duration' => $this->duration,
            'width' => $this->width,
            'height' => $this->height,
            'thumbnail' => $this->kind === ChatMessageType::Image ? $this->publicUrl($this->path) : null,
            'waveform' => $this->waveform ?? [],
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
