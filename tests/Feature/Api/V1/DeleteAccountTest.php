<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ActivityStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use App\Models\WalletTopup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Hapus akun (G3): soft-delete + anonimisasi, dengan penjaga tanggungan.
 */
final class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPassword(): User
    {
        return $this->activeUser(['password' => Hash::make('password')]);
    }

    public function test_deleting_an_account_anonymizes_and_soft_deletes_it(): void
    {
        $user = $this->userWithPassword();
        $user->createToken('sesi');
        $originalEmail = $user->email;

        $this->asUser($user)
            ->deleteJson(route('v1.me.destroy'), ['password' => 'password'])
            ->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $user->getKey()]);

        $row = User::withTrashed()->findOrFail($user->getKey());
        $this->assertSame('Akun Dihapus', $row->name);
        $this->assertNull($row->phone);
        $this->assertNotSame($originalEmail, $row->email);
        $this->assertSame("deleted+{$user->getKey()}@sekarya.invalid", $row->email);
        $this->assertSame(0, $row->tokens()->count());
    }

    public function test_the_password_must_be_correct(): void
    {
        $user = $this->userWithPassword();

        $this->asUser($user)
            ->deleteJson(route('v1.me.destroy'), ['password' => 'salah'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_current_password');

        $this->assertNotSoftDeleted('users', ['id' => $user->getKey()]);
    }

    public function test_an_account_with_pending_obligations_cannot_be_deleted(): void
    {
        $this->fundUsers = true;
        $user = $this->userWithPassword();
        WalletTopup::factory()->create(['user_id' => $user->getKey()]);

        $this->asUser($user)
            ->deleteJson(route('v1.me.destroy'), ['password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'account_has_active_obligations');
    }

    public function test_open_tasks_are_cancelled_when_the_account_is_deleted(): void
    {
        $this->fundUsers = true;
        $user = $this->userWithPassword();

        $task = Task::factory()->open()->create([
            'poster_id' => $user->getKey(),
            'bidding_closes_at' => now()->addDay(),
        ]);

        $this->asUser($user)
            ->deleteJson(route('v1.me.destroy'), ['password' => 'password'])
            ->assertNoContent();

        $this->assertSame(TaskStatus::Cancelled, $task->refresh()->status);
    }

    /**
     * Task `expired` masih menahan dana (expired tidak me-refund otomatis).
     * Dulu ia lolos dari daftar "batalkan" DAN dari penjaga, jadi akun
     * terhapus dan dananya tertinggal di tagihan `held` selamanya.
     */
    public function test_expired_tasks_are_cancelled_and_refunded_when_the_account_is_deleted(): void
    {
        $this->fundUsers = true;
        $this->seedReference();
        $user = $this->userWithPassword();

        $id = $this->asUser($user)->postJson(route('v1.tasks.store'), [
            'category_id' => $this->anyCategory()->getKey(),
            'title' => 'Cuci AC',
            'description' => 'Servis AC split.',
            'budget_min' => 150_000,
            'city' => 'Jakarta',
            'needed_at' => now()->addDays(3)->toIso8601String(),
        ])->assertCreated()->json('data.id');
        $task = Task::query()->where('ulid', $id)->firstOrFail();
        $task->forceFill(['status' => TaskStatus::Expired])->save();
        $this->assertSame(PaymentStatus::Held, $task->payment()->firstOrFail()->status);

        $this->asUser($user)
            ->deleteJson(route('v1.me.destroy'), ['password' => 'password'])
            ->assertNoContent();

        $this->assertSame(TaskStatus::Cancelled, $task->refresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $task->payment()->firstOrFail()->status);
    }

    /**
     * Pekerja yang sudah diterima tapi belum berangkat (activity `open`)
     * masih berutang pekerjaan pada task yang sudah dibiayai.
     */
    public function test_a_hired_worker_who_has_not_departed_cannot_delete_the_account(): void
    {
        $user = $this->userWithPassword();
        Activity::factory()->create(['worker_id' => $user->getKey(), 'status' => ActivityStatus::Open]);

        $this->asUser($user)
            ->deleteJson(route('v1.me.destroy'), ['password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'account_has_active_obligations');

        $this->assertNotSoftDeleted('users', ['id' => $user->getKey()]);
    }
}
