<?php

declare(strict_types=1);

namespace Tests\Feature\Web\SuperAdmin;

use App\Actions\Activity\StartActivityAction;
use App\Actions\Activity\SubmitActivityAction;
use App\Data\Activity\SubmitActivityData;
use App\Enums\ActivityStatus;
use App\Enums\TaskStatus;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskDispute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Antrean sengketa lewat web: pengelola menimbang kedua pihak lalu memutuskan
 * dengan keterangan wajib.
 */
final class DisputeTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private function openDispute(): TaskDispute
    {
        $this->seedReference();
        $this->poster = $this->activeUser(['name' => 'Bu Rina']);
        $this->worker = $this->activeUser(['name' => 'Mas Joko']);
        $task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Dealt,
        ]);
        $this->hireWorker($task, $this->worker, 180_000);
        Payment::factory()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $this->poster->getKey(),
            'amount' => 180_000,
        ]);
        $activity = $this->openActivities($task, $this->poster)->sole();
        app(StartActivityAction::class)->handle($this->bringToSite($activity));
        $submitted = app(SubmitActivityAction::class)->handle(new SubmitActivityData('beres', ['p/a.jpg']), $activity->refresh());

        return $this->raiseDispute($submitted, $this->poster, 'Lantai masih lengket di dapur.');
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('super_admin.disputes.index'))
            ->assertRedirect(route('super_admin.login'));
    }

    public function test_antrean_dan_detail_menampilkan_kedua_pihak(): void
    {
        $dispute = $this->openDispute();
        $this->actingAs($this->superAdmin(), 'admin_web');

        $this->get(route('super_admin.disputes.index'))
            ->assertOk()
            ->assertSee('Bu Rina', false)
            ->assertSee('Mas Joko', false)
            ->assertSee('180.000', false);

        $this->get(route('super_admin.disputes.show', $dispute->ulid))
            ->assertOk()
            ->assertSee('Lantai masih lengket di dapur.', false)
            ->assertSee('Mitra belum menanggapi', false)
            ->assertSee('Putuskan sengketa', false);
    }

    public function test_keterangan_wajib(): void
    {
        $dispute = $this->openDispute();
        $this->actingAs($this->superAdmin(), 'admin_web');

        $this->post(route('super_admin.disputes.resolve', $dispute->ulid), ['resolution' => 'release'])
            ->assertSessionHasErrors('note');

        $this->assertTrue($dispute->refresh()->status->isOpen());
    }

    public function test_memutuskan_tercatat_di_audit_dan_menutup_tiket(): void
    {
        $dispute = $this->openDispute();
        $this->actingAs($this->superAdmin(), 'admin_web');

        $this->post(route('super_admin.disputes.resolve', $dispute->ulid), [
            'resolution' => 'release',
            'note' => 'Foto hasil cocok dengan kesepakatan awal.',
        ])
            ->assertRedirect(route('super_admin.disputes.show', $dispute->ulid))
            ->assertSessionHas('status');

        $this->assertSame(ActivityStatus::Approved, $dispute->refresh()->activity->status);
        $this->assertSame(TaskStatus::Completed, $dispute->task->refresh()->status);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'dispute.released',
            'subject_id' => $dispute->getKey(),
            'reason' => 'Foto hasil cocok dengan kesepakatan awal.',
        ]);

        $this->get(route('super_admin.disputes.show', $dispute->ulid))
            ->assertOk()
            ->assertSee('Foto hasil cocok dengan kesepakatan awal.', false)
            ->assertDontSee('Putuskan sengketa', false);
    }
}
