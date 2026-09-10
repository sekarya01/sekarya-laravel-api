<?php

declare(strict_types=1);

namespace Tests\Feature\Web\SuperAdmin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ringkasan operasional: angka antrean + keputusan terakhir.
 *
 * Halaman ini tidak memegang data sensitif (tanpa NIK), jadi yang diuji
 * hanya kehadiran angkanya — bukan isinya.
 */
final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dasbor_menampilkan_ringkasan(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');

        $this->get(route('super_admin.dashboard'))
            ->assertOk()
            ->assertSee('Ringkasan Operasional', false)
            ->assertSee('antrean menunggu tindakanmu', false)
            ->assertSee('Keputusan terakhir', false)
            ->assertSee('Pekerja siap', false);
    }
}
