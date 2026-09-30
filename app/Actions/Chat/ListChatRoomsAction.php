<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Data\Chat\ListChatRoomsData;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Daftar room milik sendiri, aktivitas terakhir dulu.
 *
 * Diurut `updated_at` (bukan `created_at` seperti daftar lain): daftar chat
 * yang tidak naik saat ada pesan baru tidak berguna. Room per orang sedikit
 * (satu per task yang deal), jadi pergeseran urutan antarhalaman selama
 * menggulir dapat diterima — klien menyegarkan dari halaman pertama saat
 * menerima push.
 */
final class ListChatRoomsAction
{
    /** @return CursorPaginator<int, ChatRoom> */
    public function handle(ListChatRoomsData $data, User $user): CursorPaginator
    {
        return ChatRoom::query()
            ->visibleTo($user)
            ->when($data->status !== null, fn ($q) => $q->where('status', $data->status->value))
            ->forViewer($user)
            ->recentFirst()
            ->cursorPaginate($data->page->perPage);
    }
}
