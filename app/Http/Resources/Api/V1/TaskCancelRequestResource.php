<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\CancelApprovalStatus;
use App\Models\TaskCancelRequest;
use Illuminate\Http\Request;

/** @mixin TaskCancelRequest */
final class TaskCancelRequestResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'task_id' => $this->task->ulid ?? null,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'requested_at' => $this->iso($this->created_at),

            // Dua angka, bukan daftar yang harus dihitung klien: layar
            // pemberi kerja memasang "2 dari 3 sudah setuju" tanpa menelusuri
            // suara satu per satu.
            'approvals_required' => $this->whenLoaded(
                'approvals',
                fn (): int => $this->approvals->count(),
            ),
            'approvals_received' => $this->whenLoaded(
                'approvals',
                fn (): int => $this->approvals
                    ->where('status', CancelApprovalStatus::Approved)
                    ->count(),
            ),

            // Suara orang yang sedang login. Ini yang menentukan apakah popup
            // setuju/tolak masih ditawarkan kepadanya — tanpa ini, pekerja
            // yang sudah menyetujui akan ditanya lagi setiap kali membuka
            // layar, dan bisa menyetujui berkali-kali.
            'my_response' => $this->whenLoaded(
                'approvals',
                fn (): ?string => $this->approvals
                    ->firstWhere('worker_id', $request->user()?->getKey())
                    ?->status
                    ->value,
            ),
            'decided_at' => $this->iso($this->decided_at),
            'withdrawn_at' => $this->iso($this->withdrawn_at),
        ];
    }
}
