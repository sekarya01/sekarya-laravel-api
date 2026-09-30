<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use App\Enums\ChatMessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bentuk permintaan saja. Kecocokan lampiran ↔ jenis, pesan yang dibalas,
 * dan status room adalah aturan bisnis — diperiksa SendChatMessageAction.
 */
final class SendChatMessageRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $sendable = [
            ChatMessageType::Text->value, ChatMessageType::Reply->value, ChatMessageType::Image->value,
            ChatMessageType::Video->value, ChatMessageType::Audio->value, ChatMessageType::File->value,
        ];

        return [
            // UUID buatan klien — kirim ulang dengan nilai yang sama tidak
            // menggandakan pesan.
            'client_message_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'type' => ['required', 'string', Rule::in($sendable)],
            'reply_type' => ['required_if:type,reply', 'prohibited_unless:type,reply', 'nullable', 'string', Rule::in(ChatMessageType::replyBodyValues())],
            'replied_message_id' => ['required_if:type,reply', 'prohibited_unless:type,reply', 'nullable', 'string', 'size:26'],
            'attachment_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            // Teks (termasuk balasan berupa teks) wajib berisi.
            'caption' => [
                'required_if:type,text', 'required_if:reply_type,text',
                'nullable', 'string', 'max:'.(int) config('sekarya.chat.caption_max'),
            ],
        ];
    }
}
