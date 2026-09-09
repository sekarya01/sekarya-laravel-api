<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * Respons HTTP tidak boleh memuat keluaran PHP mentah.
 *
 * Ini bukan pengetatan teoretis. Di PHP 8.5, config bawaan Laravel 11 di dalam
 * `vendor/` memancarkan `Deprecated: Constant PDO::MYSQL_ATTR_SSL_CA` setiap
 * kali config dimuat tanpa cache. Dengan `display_errors` hidup, PHP menyisipkan
 * peringatan itu — beserta PATH ABSOLUT SERVER — ke badan setiap respons:
 *
 *     <br /><b>Deprecated</b>: ... in <b>/home/namaakun/public_html/vendor/...</b>
 *     {"data":[...]}
 *
 * Dua akibatnya sekaligus: responsnya bukan JSON valid lagi, dan struktur
 * direktori server terbuka untuk klien mana pun tanpa perlu autentikasi.
 *
 * `APP_DEBUG=false` TIDAK menutupnya — itu setelan Laravel, sedangkan peringatan
 * di atas dipancarkan PHP sebelum Laravel menangani apa pun. Satu-satunya
 * penutup di lapis aplikasi adalah mematikan `display_errors` di titik masuk web,
 * sebelum autoloader dan bootstrap dijalankan.
 *
 * Test ini menjaga urutan itu, bukan cuma keberadaannya: penjaga yang dipasang
 * SETELAH bootstrap tidak menutup apa pun, karena config sudah dimuat.
 */
final class ErrorOutputTest extends TestCase
{
    private function entrypoint(): string
    {
        $path = base_path('public/index.php');

        $this->assertFileExists($path, 'titik masuk web hilang');

        return (string) file_get_contents($path);
    }

    /** Nomor baris pertama yang cocok dengan pola, atau null. */
    private function lineOf(string $source, string $pattern): ?int
    {
        foreach (explode("\n", $source) as $i => $line) {
            if (preg_match($pattern, $line) === 1) {
                return $i + 1;
            }
        }

        return null;
    }

    public function test_display_errors_is_disabled_at_the_web_entrypoint(): void
    {
        $this->assertNotNull(
            $this->lineOf($this->entrypoint(), "/ini_set\(\s*'display_errors'\s*,\s*'0'\s*\)/"),
            'public/index.php harus mematikan display_errors — tanpanya setiap warning PHP '
            .'ikut terkirim ke klien beserta path absolut server',
        );
    }

    /**
     * Dibungkam dari klien, bukan dihilangkan.
     *
     * Mematikan display_errors tanpa menghidupkan log_errors berarti galatnya
     * tidak tercatat di mana pun — menukar kebocoran informasi dengan kebutaan.
     */
    public function test_the_errors_are_still_logged(): void
    {
        $this->assertNotNull(
            $this->lineOf($this->entrypoint(), "/ini_set\(\s*'log_errors'\s*,\s*'1'\s*\)/"),
            'public/index.php harus tetap mencatat galat ke log server',
        );
    }

    public function test_the_guard_runs_before_the_framework_is_bootstrapped(): void
    {
        $source = $this->entrypoint();

        $guard = $this->lineOf($source, "/ini_set\(\s*'display_errors'/");
        $bootstrap = $this->lineOf($source, '/require(_once)?\s+__DIR__\..\/\.\.\/bootstrap\/app\.php/');
        $autoload = $this->lineOf($source, '/require\s+__DIR__\..\/\.\.\/vendor\/autoload\.php/');

        $this->assertNotNull($guard, 'penjaga display_errors tidak ada');
        $this->assertNotNull($bootstrap, 'baris bootstrap tidak dikenali');
        $this->assertNotNull($autoload, 'baris autoload tidak dikenali');

        $this->assertLessThan(
            $bootstrap,
            $guard,
            'penjaga harus berjalan SEBELUM bootstrap — setelahnya config sudah dimuat '
            .'dan peringatannya sudah terpancar',
        );
        $this->assertLessThan(
            $autoload,
            $guard,
            'penjaga harus berjalan sebelum autoloader, supaya galat pemuatan paket '
            .'pun tidak terkirim ke klien',
        );
    }

    /**
     * Konfigurasi aplikasi sendiri tidak boleh memakai konstanta yang sudah
     * ditinggalkan. Yang di dalam `vendor/` di luar kendali kita; yang ini tidak.
     */
    public function test_the_apps_own_config_uses_the_modern_pdo_constant(): void
    {
        $config = (string) file_get_contents(base_path('config/database.php'));

        $this->assertStringNotContainsString(
            'PDO::MYSQL_ATTR_SSL_CA',
            $config,
            'pakai Pdo\Mysql::ATTR_SSL_CA — bentuk PDO:: sudah deprecated sejak PHP 8.5',
        );
    }

    /** Produksi tidak boleh menyalakan halaman debug Laravel. */
    public function test_the_production_example_keeps_debug_off(): void
    {
        $env = (string) file_get_contents(base_path('.env.production.example'));

        $this->assertMatchesRegularExpression(
            '/^APP_DEBUG=false$/m',
            $env,
            'APP_DEBUG harus false di contoh produksi',
        );
        $this->assertMatchesRegularExpression(
            '/^APP_ENV=production$/m',
            $env,
            'APP_ENV harus production di contoh produksi',
        );
    }
}
