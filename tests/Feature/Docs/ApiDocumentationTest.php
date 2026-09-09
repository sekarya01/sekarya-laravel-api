<?php

declare(strict_types=1);

namespace Tests\Feature\Docs;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Kontrak API dan dokumentasinya harus tetap seiring.
 *
 * `docs/openapi.yaml` di proyek ini adalah KONTRAK, bukan produk sampingan dari
 * anotasi — tidak ada yang menghasilkannya dari kode, jadi tidak ada pula yang
 * memberitahu ketika ia tertinggal. Sebuah endpoint baru yang lupa dicatat
 * tidak menimbulkan galat apa pun: ia hanya tidak pernah muncul di referensi,
 * dan orang yang memakainya baru tahu setelah menebak.
 *
 * Kelas ini yang mengubah "ingat perbarui dokumentasi" dari disiplin menjadi
 * suite yang gagal.
 */
final class ApiDocumentationTest extends TestCase
{
    /** @return list<array{method: string, path: string, name: string|null}> */
    private function apiRoutes(): array
    {
        $out = [];

        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/v1')) {
                continue;
            }

            $path = substr($uri, strlen('api/v1'));

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $out[] = [
                    'method' => strtolower($method),
                    'path' => $path === '' ? '/' : $path,
                    'name' => $route->getName(),
                ];
            }
        }

        return $out;
    }

    /**
     * Operasi yang tercatat di spec.
     *
     * Dibaca dengan regex, bukan parser YAML: proyek ini tidak memasang
     * pustaka YAML, dan menambah satu dependensi hanya untuk membaca dua
     * tingkat indentasi tidak sepadan. Bentuk yang dicari sempit — dua spasi
     * untuk path, empat untuk verb — jadi ia tidak bisa salah tangkap.
     *
     * @return list<array{method: string, path: string}>
     */
    private function documentedOperations(): array
    {
        $spec = file_get_contents(base_path('docs/openapi.yaml'));

        $this->assertIsString($spec);

        $lines = explode("\n", $spec);
        $inPaths = false;
        $currentPath = null;
        $out = [];

        foreach ($lines as $line) {
            if ($line === 'paths:') {
                $inPaths = true;

                continue;
            }

            // Kunci tingkat atas berikutnya menutup blok paths.
            if ($inPaths && $line !== '' && ! str_starts_with($line, ' ')) {
                $inPaths = false;
            }

            if (! $inPaths) {
                continue;
            }

            if (preg_match('/^  (\/\S*):\s*$/', $line, $m) === 1) {
                $currentPath = $m[1];

                continue;
            }

            if ($currentPath !== null
                && preg_match('/^    (get|post|put|patch|delete):\s*$/', $line, $m) === 1) {
                $out[] = ['method' => $m[1], 'path' => $currentPath];
            }
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $operations */
    private function keys(array $operations): array
    {
        return array_map(
            static fn (array $op): string => strtoupper($op['method']).' '.$op['path'],
            $operations,
        );
    }

    /**
     * Setiap endpoint yang benar-benar ada HARUS tercatat.
     *
     * Ini arah yang paling penting: rute yang dilupakan tetap bisa dipanggil
     * siapa pun yang menemukannya, tanpa satu baris pun keterangan tentang
     * bentuk permintaan, batas laju, maupun galatnya.
     */
    public function test_every_api_route_is_documented(): void
    {
        $documented = $this->keys($this->documentedOperations());
        $missing = array_values(array_diff($this->keys($this->apiRoutes()), $documented));

        $this->assertSame([], $missing, sprintf(
            "Endpoint berikut ada di routes/api.php tapi tidak di docs/openapi.yaml:\n  %s",
            implode("\n  ", $missing),
        ));
    }

    /**
     * Dan sebaliknya: spec tidak boleh menjanjikan endpoint yang sudah tidak ada.
     *
     * Dokumentasi yang menjanjikan lebih dari yang ada lebih merugikan daripada
     * dokumentasi yang kurang — orang membangun klien di atasnya lebih dulu,
     * lalu menemukan `404` di produksi.
     */
    public function test_the_spec_does_not_promise_endpoints_that_do_not_exist(): void
    {
        $real = $this->keys($this->apiRoutes());
        $phantom = array_values(array_diff($this->keys($this->documentedOperations()), $real));

        $this->assertSame([], $phantom, sprintf(
            "Endpoint berikut ada di docs/openapi.yaml tapi tidak di routes/api.php:\n  %s",
            implode("\n  ", $phantom),
        ));
    }

    /**
     * Kode galat mesin adalah bagian dari kontrak: klien bercabang padanya.
     * Sebuah kode yang tidak terdaftar berarti klien tidak punya cara tahu ia
     * bisa muncul.
     */
    public function test_every_domain_error_code_is_listed_in_the_spec(): void
    {
        $spec = (string) file_get_contents(base_path('docs/openapi.yaml'));
        $missing = [];

        foreach (glob(app_path('Exceptions/Domain/*.php')) ?: [] as $file) {
            $source = (string) file_get_contents($file);

            if (preg_match("/errorCode\(\): string\s*\{\s*return '([a-z_]+)'/", $source, $m) !== 1) {
                continue;
            }

            if (! str_contains($spec, '- '.$m[1])) {
                $missing[] = $m[1].' ('.basename($file).')';
            }
        }

        $this->assertSame([], $missing, sprintf(
            "Kode galat berikut dilempar aplikasi tapi tidak terdaftar di enum DomainError:\n  %s",
            implode("\n  ", $missing),
        ));
    }

    /**
     * Panduan manusia juga harus menyebut semuanya.
     *
     * `docs/openapi.yaml` dibaca mesin dan generator klien; `docs/API.md` yang
     * dibaca orang saat pertama kali menyentuh API ini. Endpoint yang hanya ada
     * di salah satunya tetap endpoint yang tidak ditemukan.
     */
    public function test_every_endpoint_appears_in_the_human_guide(): void
    {
        $guide = (string) file_get_contents(base_path('docs/API.md'));

        $start = strpos($guide, '## Ringkasan endpoint');
        $end = strpos($guide, '## Kode galat');

        $this->assertIsInt($start);
        $this->assertIsInt($end);

        $summary = substr($guide, $start, $end - $start);
        $missing = [];

        foreach ($this->apiRoutes() as $route) {
            $row = sprintf('| `%s` | `%s` |', strtoupper($route['method']), $route['path']);

            if (! str_contains($summary, $row)) {
                $missing[] = trim($row, '| ');
            }
        }

        $this->assertSame([], $missing, sprintf(
            "Endpoint berikut tidak ada di tabel ringkasan docs/API.md:\n  %s",
            implode("\n  ", $missing),
        ));
    }

    /** Jumlah yang disebut panduan harus benar — angka yang meleset menyesatkan. */
    public function test_the_guide_states_the_right_endpoint_count(): void
    {
        $guide = (string) file_get_contents(base_path('docs/API.md'));

        $this->assertStringContainsString(
            sprintf('**%d endpoint,', count($this->apiRoutes())),
            $guide,
        );
    }
}
