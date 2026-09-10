<?php

declare(strict_types=1);

namespace Tests\Feature\Web\SuperAdmin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guard filter: tombol cari mati sampai ≥1 field diubah dari bawaan.
 *
 * Aturannya hidup di JavaScript (FormData vs snapshot awal), jadi yang bisa
 * diuji dari sisi server: setiap form filter memakai `data-guard` dan punya
 * tombol submit. Perilaku submit kosong tetap diterima server (aturan "null
 * dilewati, daftar bawaan tampil") — dibuktikan test index tanpa parameter
 * di kelas lain.
 */
final class FilterGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> rute index => halaman */
    private function guardedPages(): array
    {
        return [
            'super_admin.verifications.index' => 'verifikasi',
            'super_admin.payments.index' => 'transfer',
            'super_admin.users.index' => 'pengguna',
            'super_admin.workers.index' => 'pekerja',
            'super_admin.audit.index' => 'audit',
        ];
    }

    public function test_semua_form_filter_memakai_guard(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');

        foreach ($this->guardedPages() as $route => $label) {
            $response = $this->get(route($route))->assertOk();
            $response->assertSee('<form method="GET" data-guard', false);
            $response->assertSee('type="submit"', false);
            // Select-select tinggal di drawer kanan; input teks tetap inline.
            $response->assertSee('data-open-drawer="filterDrawer"', false);
            $response->assertSee('id="filterDrawer"', false);
        }
    }

    /**
     * Submit kosong persis seperti browser: semua field terkirim sebagai ''.
     *
     * Laravel mengubahnya jadi null — tanpa `nullable`, aturan `string`,
     * `in`, `size`, dan `boolean` menolaknya dan daftar tak pernah tampil.
     * Inilah yang terjadi di layar pengguna sebelum diperbaiki.
     */
    public function test_submit_kosong_tidak_gagal_validasi(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');

        $empties = [
            'super_admin.verifications.index' => ['status' => '', 'type' => ''],
            'super_admin.payments.index' => ['status' => ''],
            'super_admin.users.index' => ['status' => '', 'email' => '', 'gender' => '', 'ready' => '', 'ulid' => ''],
            'super_admin.workers.index' => ['ready_to_work' => '', 'gender' => '', 'city' => '', 'province' => ''],
            'super_admin.audit.index' => ['action' => ''],
        ];

        foreach ($empties as $route => $params) {
            $this->get(route($route, $params))
                ->assertOk()
                ->assertSessionHasNoErrors();
        }
    }
}
