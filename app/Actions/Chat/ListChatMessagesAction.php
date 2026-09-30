<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Data\Chat\ListChatMessagesData;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\Chat\ChatAccess;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Pesan sebuah room, terbaru dulu — termasuk yang dihapus (sebagai kerangka
 * "Pesan dihapus", supaya urutan dan kutipan balasan tetap utuh).
 *
 * `after_id` = sinkron sesudah push: hanya yang lebih baru dari id itu.
 * Room yang `expired` tetap terbaca.
 */
final class ListChatMessagesAction
{
    public function __construct(private readonly ChatAccess $access) {}

    /** @return CursorPaginator<int, ChatMessage> */
    public function handle(ChatRoom $room, ListChatMessagesData $data, User $user): CursorPaginator
    {
        $this->access->participant($room, $user);
        $room->loadMissing('participants.user');

        $page = ChatMessage::query()
            ->withTrashed()
            ->where('room_id', $room->getKey())
            ->when($data->afterId !== null, fn ($q) => $q->where('id', '>', $data->afterId))
            ->with(['attachment', 'replied.attachment'])
            ->latestFirst()
            ->cursorPaginate($data->page->perPage);

        // Satu room untuk semua baris: resource membaca peserta (pengirim,
        // status baca) dari sini tanpa satu kueri per pesan.
        $page->getCollection()->each(fn (ChatMessage $m) => $m->setRelation('room', $room));

        return $page;
    }
}
