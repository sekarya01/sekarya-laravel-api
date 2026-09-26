<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

final class ShowTaskController
{
    public function __invoke(Request $request, Task $task): TaskResource
    {
        return TaskResource::make(
            // activities.worker wajib: mobile memakai worker.name untuk
            // kartu mitra + syarat tampil stepper status pengerjaan.
            //
            // myBid sama wajibnya, dan dibatasi ke penawar yang sedang login
            // persis seperti di feed: tanpa itu `my_bid` selalu null di sini,
            // dan mitra yang membuka ulang task yang sudah ia tawar melihat
            // "Ajukan Penawaran" hidup lagi seolah belum pernah menawar.
            $task->load([
                // workers.skills: keahlian pekerja untuk layar profilnya.
                'category', 'poster', 'workers.skills', 'skills', 'payment', 'activities.worker',
                // cancel_request pending: popup persetujuan di Detail Kerjaan.
                'pendingCancelRequest.approvals',
                'myBid' => fn (Relation $q) => $q->where('bidder_id', $request->user()?->getKey()),
            ])->loadExists([
                // Apakah saya menyimpan tugas ini (B11).
                'bookmarks as bookmarked' => fn ($q) => $q->where('user_id', $request->user()?->getKey()),
            ]),
        );
    }
}
