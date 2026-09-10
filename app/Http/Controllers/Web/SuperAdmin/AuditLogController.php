<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Enums\AdminAction;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Baca jejak audit (append-only, tanpa updated_at).
 *
 * API belum punya endpoint untuk ini — dasbor membaca langsung dari basis
 * data, sama seperti kueri SQL di docs/API.md bagian 14.
 */
final class AuditLogController
{
    public function __invoke(Request $request): View
    {
        $request->validate([
            'action' => ['sometimes', 'string', 'max:60'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = max(1, min($request->integer('per_page', 20), 50));
        $filterAction = $request->string('action')->value() ?: '';

        $logs = AdminAuditLog::query()
            ->with('admin')
            ->when($filterAction !== '', fn ($q) => $q->where('action', $filterAction))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('super_admin.audit.index', [
            'logs' => $logs,
            'filterAction' => $filterAction,
            'actions' => AdminAction::cases(),
        ]);
    }
}
