<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Admin;

use App\Actions\Admin\WorkerInvite\CreateWorkerInviteCodeAction;
use App\Data\Admin\CreateWorkerInviteCodeData;
use App\Models\AdminAuditLog;
use App\Models\WorkerInviteCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Kode undangan dan jejak penerbitnya lahir bersama atau tidak sama sekali.
 */
final class CreateWorkerInviteCodeActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_code_is_rolled_back_when_its_audit_trail_cannot_be_written(): void
    {
        // Jejak gagal SESUDAH kodenya tertulis.
        AdminAuditLog::creating(static function (): never {
            throw new RuntimeException('jejak audit gagal');
        });

        try {
            app(CreateWorkerInviteCodeAction::class)->handle(new CreateWorkerInviteCodeData(maxUses: 10), $this->activeAdmin());
            $this->fail('Jejak audit seharusnya gagal.');
        } catch (RuntimeException $e) {
            $this->assertSame('jejak audit gagal', $e->getMessage());
        } finally {
            AdminAuditLog::flushEventListeners();
        }

        $this->assertSame(0, WorkerInviteCode::query()->count());
    }
}
