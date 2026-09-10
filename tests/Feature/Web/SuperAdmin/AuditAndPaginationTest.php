<?php

declare(strict_types=1);

namespace Tests\Feature\Web\SuperAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jejak audit hanya-baca + pagination bernomor di semua daftar.
 */
final class AuditAndPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_jejak_tampil_dan_tersaring_per_tindakan(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->activeUser();

        $this->post(route('super_admin.users.suspend', $user->ulid), [
            'reason' => 'Melaporkan transfer palsu dua kali berturut-turut.',
        ]);

        $this->get(route('super_admin.audit.index'))
            ->assertOk()
            ->assertSee('user.suspended', false);

        $this->get(route('super_admin.audit.index', ['action' => 'user.suspended']))
            ->assertOk()
            ->assertSee('user.suspended', false);
    }

    public function test_pagination_bernomor_dengan_prev_next(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        User::factory()->count(25)->create();

        $first = $this->get(route('super_admin.users.index'))->assertOk();
        $first->assertSee('Berikutnya', false)
            ->assertSee('page=2', false)
            ->assertSee('Halaman 1 dari', false);

        $this->get(route('super_admin.users.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Sebelumnya', false);
    }
}
