<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Logging\Axiom\AxiomClient;
use App\Logging\Axiom\AxiomLogger;
use App\Logging\Axiom\Redactor;
use Illuminate\Console\Command;

/**
 * Memeriksa dan MEMBUKTIKAN konfigurasi Axiom.
 *
 * `--audit` adalah alasan utama perintah ini ada. Klaim "PII sudah disaring"
 * tidak bisa diverifikasi dengan membaca kode — ia harus dijalankan. Perintah
 * ini melewatkan payload sintetis yang memuat setiap jenis data berbahaya yang
 * ada di aplikasi ini (kata sandi, kode verifikasi, NIK, nomor rekening, token
 * Sanctum, alamat e-mail, nomor HP) lalu MENCETAK hasil akhirnya, sehingga
 * yang benar-benar keluar dari server bisa dilihat dengan mata.
 *
 * Jalankan ini setiap kali `config/axiom.php` disentuh, dan setiap kali sebuah
 * endpoint baru menerima field baru.
 */
final class AxiomCommand extends Command
{
    protected $signature = 'sekarya:axiom
        {--audit : Cetak hasil penyaringan payload sintetis, tanpa mengirim apa pun}
        {--ping : Kirim satu event uji ke Axiom}';

    protected $description = 'Periksa konfigurasi Axiom, audit penyaringan PII, kirim event uji';

    public function handle(AxiomLogger $logger, Redactor $redactor, AxiomClient $client): int
    {
        $this->status();

        if ($this->option('audit')) {
            $this->audit($redactor);
        }

        if ($this->option('ping')) {
            return $this->ping($logger, $client);
        }

        if (! $this->option('audit')) {
            $this->newLine();
            $this->line('Gunakan <info>--audit</info> untuk melihat hasil penyaringan, '
                .'<info>--ping</info> untuk mengirim event uji.');
        }

        return self::SUCCESS;
    }

    private function status(): void
    {
        $token = (string) config('axiom.token', '');

        $this->components->twoColumnDetail('<fg=cyan>Axiom</>', '');
        $this->components->twoColumnDetail('enabled', config('axiom.enabled') ? '<fg=green>true</>' : '<fg=yellow>false</>');
        $this->components->twoColumnDetail('dataset', (string) config('axiom.dataset'));
        $this->components->twoColumnDetail('endpoint', (string) config('axiom.endpoint'));

        // Token TIDAK PERNAH dicetak. Empat karakter terakhir cukup untuk
        // memastikan "yang terpasang adalah token yang saya kira", dan tidak
        // cukup untuk dipakai siapa pun yang membaca layar atau log CI.
        $this->components->twoColumnDetail(
            'token',
            $token === ''
                ? '<fg=red>belum diisi</>'
                : '<fg=green>terpasang</> (…'.substr($token, -4).', '.strlen($token).' karakter)',
        );

        $this->components->twoColumnDetail('delivery', (string) config('axiom.delivery'));
        $this->components->twoColumnDetail('ip_mode', (string) config('axiom.privacy.ip_mode'));
        $this->components->twoColumnDetail('request_payload', (string) config('axiom.privacy.request_payload'));
        $this->components->twoColumnDetail('response_payload', (string) config('axiom.privacy.response_payload'));
        $this->components->twoColumnDetail('pseudonymize', config('axiom.privacy.pseudonymize') ? 'true' : 'false');

        $enabled = array_keys(array_filter((array) config('axiom.capture', [])));
        $this->components->twoColumnDetail('capture', implode(', ', $enabled) ?: '<fg=yellow>tidak ada</>');

        $this->warnAboutDuplicateEnvKeys();
    }

    /**
     * Kunci `AXIOM_*` yang muncul lebih dari sekali di `.env`.
     *
     * Kegagalan ini tidak bersuara dan sangat mudah terjadi: Dotenv memakai
     * kemunculan TERAKHIR, jadi sebuah placeholder kosong yang tertinggal di
     * bawah akan menimpa token yang sudah benar di atasnya. Gejalanya cuma
     * "kredensial Axiom belum diisi" padahal token jelas-jelas terlihat di
     * berkas — dan itu butuh membaca log untuk ketahuan.
     *
     * Hanya NAMA kunci yang dikembalikan; nilainya tidak pernah dibaca keluar.
     *
     * @return array<int, string>
     */
    public static function duplicateEnvKeys(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $counts = [];

        foreach ((array) file($path, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^\s*(AXIOM_[A-Z0-9_]+)\s*=/', (string) $line, $m) === 1) {
                $counts[$m[1]] = ($counts[$m[1]] ?? 0) + 1;
            }
        }

        return array_keys(array_filter($counts, static fn (int $n): bool => $n > 1));
    }

    private function warnAboutDuplicateEnvKeys(): void
    {
        // `environmentFilePath()`, bukan `base_path('.env')`: ia menghormati
        // berkas env yang dipindah atau diganti nama, dan bisa diarahkan di test.
        $duplicates = self::duplicateEnvKeys($this->laravel->environmentFilePath());

        if ($duplicates === []) {
            return;
        }

        $this->newLine();
        $this->components->error(sprintf(
            'Kunci ganda di .env: %s. Dotenv memakai yang TERAKHIR, jadi baris di '
            .'bawah menimpa yang di atas — sebuah placeholder kosong akan '
            .'mengosongkan nilai yang sudah benar. Sisakan satu saja per kunci.',
            implode(', ', $duplicates),
        ));
    }

    /**
     * Payload sintetis yang meniru setiap field berbahaya di aplikasi ini.
     *
     * Nilai-nilai di bawah ini SENGAJA palsu tapi berbentuk asli, supaya
     * penyaring pola benar-benar diuji: token Sanctum harus berbentuk
     * `<id>|<40 karakter>`, NIK harus 16 angka, nomor HP harus berawalan 08.
     */
    private function audit(Redactor $redactor): void
    {
        $sample = [
            'email' => 'budi.prasetyo@contoh.test',
            'password' => 'RahasiaKuat2026',
            'password_confirmation' => 'RahasiaKuat2026',
            'code' => '623862',
            'name' => 'Budi Prasetyo',
            'phone' => '+628111222333',
            'document_number' => '3271012345678901',
            'account_number' => '1234567890',
            'account_holder_name' => 'BUDI PRASETYO',
            'bio' => 'Tukang listrik 10 tahun pengalaman, siap dipanggil kapan saja.',
            'title' => 'Perbaiki AC bocor di Bintaro',
            'category_id' => '01JBQ7XK2M3N4P5Q6R7S8T9V0W',
            'price' => 250000,
            'nested' => [
                'gateway_payload' => ['va' => '8808123456789012'],
                'callback_url' => 'https://mitra.test/cb?token=abc123secret&id=7',
            ],
            'catatan_bebas' => 'hubungi budi di budi@contoh.test atau 081122334455, NIK 3271012345678901',
            'authorization' => 'Bearer 42|kZ3nQm8vXpL2rT7yW1bC5dF9gH0jK4mN6pQ8sU3x',
        ];

        $this->newLine();
        $this->components->info('Audit penyaringan — kiri: yang dikirim aplikasi, kanan: yang benar-benar keluar');

        $result = $redactor->payload($sample);

        $rows = [];

        foreach ($sample as $key => $value) {
            $before = is_array($value) ? json_encode($value) : (string) $value;

            // Kunci identitas berganti nama menjadi `<kunci>_sha`, jadi hasilnya
            // dicari dengan kedua nama.
            $matching = array_filter(
                $result,
                static fn (mixed $v, string|int $k): bool => $k === $key || str_starts_with((string) $k, $key.'_'),
                ARRAY_FILTER_USE_BOTH,
            );

            $after = implode('  ', array_map(
                static fn (string|int $k, mixed $v): string => $k.'='.(is_array($v) ? json_encode($v) : (string) $v),
                array_keys($matching),
                $matching,
            ));

            $rows[] = [$key, mb_strimwidth((string) $before, 0, 46, '…'), $after];
        }

        $this->table(['field', 'sebelum', 'sesudah'], $rows);

        $this->newLine();
        $this->components->warn(
            'Periksa kolom kanan. Tidak boleh ada satu pun kata sandi, kode verifikasi, '
            .'NIK, nomor rekening, token, alamat e-mail, atau nomor telepon yang masih terbaca.',
        );

        // Header diuji terpisah karena memakai allowlist, bukan denylist.
        $this->newLine();
        $this->components->info('Header (allowlist)');

        $headers = $redactor->headers([
            'authorization' => ['Bearer 42|kZ3nQm8vXpL2rT7yW1bC5dF9gH0jK4mN6pQ8sU3x'],
            'cookie' => ['laravel_session=eyJpdiI6IkFB'],
            'user-agent' => ['Sekarya/1.2.0 (Android 14)'],
            'content-type' => ['application/json'],
            'x-api-key' => ['super-secret'],
        ]);

        $this->table(
            ['header', 'diteruskan?'],
            [
                ['authorization', isset($headers['authorization']) ? '<fg=red>YA — BUG</>' : '<fg=green>tidak</>'],
                ['cookie', isset($headers['cookie']) ? '<fg=red>YA — BUG</>' : '<fg=green>tidak</>'],
                ['x-api-key', isset($headers['x-api-key']) ? '<fg=red>YA — BUG</>' : '<fg=green>tidak</>'],
                ['user-agent', isset($headers['user-agent']) ? '<fg=green>ya (aman)</>' : 'tidak'],
                ['content-type', isset($headers['content-type']) ? '<fg=green>ya (aman)</>' : 'tidak'],
            ],
        );
    }

    private function ping(AxiomLogger $logger, AxiomClient $client): int
    {
        if (! $logger->enabled()) {
            $this->components->error('AXIOM_ENABLED=false — tidak ada yang dikirim.');

            return self::FAILURE;
        }

        $ok = $client->send([[
            '_time' => now()->format('Y-m-d\TH:i:s.up'),
            'service' => config('axiom.service'),
            'env' => config('app.env'),
            'level' => 'info',
            'kind' => 'axiom',
            'event' => 'axiom.ping',
            'request_id' => $logger->requestId(),
            'note' => 'dikirim oleh artisan sekarya:axiom --ping',
        ]]);

        if (! $ok) {
            $this->components->error('Gagal. Alasannya ada di storage/logs/laravel.log (prefix [axiom]).');

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Terkirim. Cari di Axiom: dataset "%s", event == "axiom.ping", request_id == "%s".',
            (string) config('axiom.dataset'),
            $logger->requestId(),
        ));

        return self::SUCCESS;
    }
}
