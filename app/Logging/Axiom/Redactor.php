<?php

declare(strict_types=1);

namespace App\Logging\Axiom;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Satu-satunya pintu keluar data ke Axiom.
 *
 * Tidak ada satu pun nilai dari request, database, atau exception yang boleh
 * masuk ke sebuah event tanpa melewati kelas ini.
 *
 * Tiga lapis, dan ketiganya diperlukan:
 *
 *  1. NAMA KUNCI — denylist (`password`, `nik`, `account_number`, …).
 *     Cepat dan tegas, tapi hanya sekuat kelengkapan daftarnya.
 *
 *  2. POLA NILAI — token Sanctum, JWT, header Basic/Bearer, kunci PEM,
 *     APP_KEY, alamat e-mail, nomor HP, 16 angka berurutan.
 *     Ini yang menutup lubang lapis 1: rahasia yang muncul di kunci yang
 *     belum pernah terlihat — di pesan exception, di query string, di dalam
 *     teks bebas — tetap tertangkap. Setiap kali skema payload berubah,
 *     lapis 1 tertinggal; lapis 2 tidak.
 *
 *  3. BATAS UKURAN — panjang string, kedalaman, jumlah elemen.
 *     Event log yang besar hampir selalu berarti ada data mentah terbawa.
 *     Memotongnya membatasi kerusakan dari apa pun yang lolos lapis 1 dan 2.
 *
 * Identitas tidak dibuang begitu saja tapi dipseudonimkan dengan
 * HMAC-SHA256 berkunci APP_KEY. Hash telanjang tidak cukup: ruang tebakan
 * sebuah alamat e-mail atau nomor HP Indonesia kecil, jadi SHA256 tanpa kunci
 * bisa dibalik dengan kamus. APP_KEY tidak pernah ada di Axiom, jadi
 * pseudonimnya hanya bisa dicocokkan kembali dari dalam server ini.
 */
final class Redactor
{
    public const REDACTED = '[redacted]';

    /** Ditandai supaya jelas di Axiom bahwa hilangnya nilai itu disengaja. */
    private const SCRUBBED = '[scrubbed]';

    public function __construct(private readonly Config $config) {}

    /**
     * Payload aplikasi (query string, body request, konteks log).
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function payload(array $data): array
    {
        return $this->walk($data, 0);
    }

    /**
     * Header request — ALLOWLIST, bukan denylist.
     *
     * `Authorization` dan `Cookie` ada di setiap request terautentikasi, di
     * nama yang bisa diprediksi. Satu kelalaian pada denylist langsung berarti
     * token pengguna terkirim ke pihak ketiga, jadi di sini hanya nama yang
     * disebut eksplisit yang boleh lewat.
     *
     * @param  array<string, array<int, string|null>|string|null>  $headers
     * @return array<string, string>
     */
    public function headers(array $headers): array
    {
        /** @var array<int, string> $allowed */
        $allowed = (array) $this->config->get('axiom.allowed_headers', []);
        $allowed = array_map('strtolower', $allowed);

        $out = [];

        foreach ($headers as $name => $value) {
            $key = strtolower((string) $name);

            if (! in_array($key, $allowed, true)) {
                continue;
            }

            $flat = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;

            $out[$key] = $this->text($flat);
        }

        return $out;
    }

    /**
     * String bebas: pesan exception, SQL, pesan validasi, URL.
     *
     * Selalu lewati fungsi ini, bukan hanya payload — pesan exception adalah
     * tempat rahasia paling sering bocor tanpa sengaja
     * ("SQLSTATE... for key 'users_email_unique'" berisi alamat e-mail).
     */
    public function text(string $value, ?int $maxLength = null): string
    {
        if ($this->boolConfig('axiom.privacy.scrub_value_patterns', true)) {
            $value = $this->scrub($value);
        }

        return $this->truncate($value, $maxLength);
    }

    /**
     * Jaring terakhir sebelum sebuah event dikirim.
     *
     * Menerapkan HANYA lapis 2 dan 3 (pola nilai + batas ukuran) ke setiap
     * string di dalam event, tanpa aturan nama kunci. Aturan nama kunci sudah
     * dipakai di tempat data itu diambil, dan menerapkannya dua kali justru
     * merusak: `job.name` akan dipseudonimkan seperti nama orang.
     *
     * Gunanya: sebuah listener baru yang lupa menyaring payload-nya tetap
     * tidak bisa membocorkan token atau alamat e-mail, karena polanya
     * tertangkap di sini.
     *
     * @param  array<array-key, mixed>  $event
     * @return array<array-key, mixed>
     */
    public function scrubDeep(array $event, int $depth = 0): array
    {
        $maxDepth = (int) $this->config->get('axiom.limits.max_depth', 4);

        if ($depth >= $maxDepth + 2) {
            return ['_truncated' => 'max_depth'];
        }

        $out = [];

        foreach ($event as $key => $value) {
            $out[$key] = match (true) {
                is_array($value) => $this->scrubDeep($value, $depth + 1),
                is_string($value) => $this->text($value),
                is_int($value), is_float($value), is_bool($value), $value === null => $value,
                is_object($value) => '[object:'.$value::class.']',
                default => '['.gettype($value).']',
            };
        }

        return $out;
    }

    /**
     * Pseudonim stabil: nilai yang sama selalu menghasilkan penanda yang sama,
     * sehingga "orang yang sama" bisa dilacak antar event tanpa satu pun
     * nilai asli keluar dari server.
     */
    public function pseudonym(string $value): string
    {
        if (! $this->boolConfig('axiom.privacy.pseudonymize', true)) {
            return self::REDACTED;
        }

        $key = (string) $this->config->get('app.key', '');

        // Tanpa APP_KEY, hash tidak berkunci dan bisa dibalik dengan kamus —
        // lebih baik tidak mengirim penanda apa pun daripada mengirim yang
        // memberi rasa aman palsu.
        if ($key === '') {
            return self::REDACTED;
        }

        $normalized = mb_strtolower(trim($value));

        return substr(hash_hmac('sha256', $normalized, $key), 0, 16);
    }

    /**
     * Alamat e-mail -> pseudonim + (opsional) domainnya.
     *
     * Domain dipisah karena berguna dan tidak menunjuk orang: kegagalan kirim
     * surat hampir selalu berkelompok per domain penerima.
     *
     * @return array{sha: string, domain?: string}
     */
    public function email(string $value): array
    {
        $out = ['sha' => $this->pseudonym($value)];

        if (! $this->boolConfig('axiom.privacy.keep_email_domain', true)) {
            return $out;
        }

        $at = strrpos($value, '@');

        if ($at !== false) {
            $domain = mb_strtolower(substr($value, $at + 1));

            if ($domain !== '') {
                $out['domain'] = $this->truncate($domain, 100);
            }
        }

        return $out;
    }

    /**
     * IP menurut `axiom.privacy.ip_mode`.
     *
     * Default `prefix_hash` mengirim /24 (v4) atau /48 (v6) plus HMAC penuh.
     * Gabungan itu menjawab dua pertanyaan yang benar-benar dipakai saat
     * menyelidiki abuse — "dari jaringan mana?" dan "apakah ini IP yang sama
     * dengan tadi?" — tanpa menyimpan alamat yang menunjuk satu pelanggan.
     *
     * @return array<string, string>
     */
    public function ip(?string $ip): array
    {
        $mode = (string) $this->config->get('axiom.privacy.ip_mode', 'prefix_hash');

        if ($ip === null || $ip === '' || $mode === 'none') {
            return [];
        }

        return match ($mode) {
            'full' => ['ip' => $ip],
            'hash' => ['ip_sha' => $this->pseudonym($ip)],
            'prefix' => array_filter(['ip_prefix' => $this->ipPrefix($ip)]),
            default => array_filter([
                'ip_prefix' => $this->ipPrefix($ip),
                'ip_sha' => $this->pseudonym($ip),
            ]),
        };
    }

    /**
     * Jejak tumpukan tanpa ARGUMEN pemanggilan.
     *
     * `Throwable::getTraceAsString()` menyertakan argumen skalar, sehingga
     * sebuah kata sandi yang dilempar ke `Hash::check('rahasia', …)` muncul
     * apa adanya di dalam trace. Karena itu trace dibangun ulang dari
     * `getTrace()` dan hanya berkas, baris, kelas, dan nama fungsi yang
     * diambil — `args` tidak pernah dibaca.
     *
     * @param  array<int, array<string, mixed>>  $frames
     * @return array<int, string>
     */
    public function trace(array $frames): array
    {
        $max = (int) $this->config->get('axiom.limits.max_trace_frames', 30);
        $base = base_path();
        $out = [];

        foreach (array_slice($frames, 0, $max) as $frame) {
            $file = isset($frame['file']) ? (string) $frame['file'] : '[internal]';

            // Path relatif: absolut membocorkan struktur direktori server dan
            // sering juga nama pengguna sistem.
            if (str_starts_with($file, $base)) {
                $file = ltrim(substr($file, strlen($base)), '/');
            }

            $call = isset($frame['class'], $frame['type'])
                ? $frame['class'].$frame['type'].($frame['function'] ?? '?')
                : (string) ($frame['function'] ?? '?');

            $out[] = $file.':'.($frame['line'] ?? 0).' '.$call.'()';
        }

        return $out;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function walk(array $data, int $depth): array
    {
        $maxDepth = (int) $this->config->get('axiom.limits.max_depth', 4);
        $maxItems = (int) $this->config->get('axiom.limits.max_array_items', 50);

        if ($depth >= $maxDepth) {
            return ['_truncated' => 'max_depth'];
        }

        $out = [];
        $seen = 0;

        foreach ($data as $key => $value) {
            if ($seen >= $maxItems) {
                $out['_truncated'] = 'max_array_items:'.count($data);
                break;
            }

            $seen++;

            // Kunci numerik adalah indeks daftar, bukan nama field — tidak ada
            // yang bisa disimpulkan darinya, jadi hanya nilainya yang diperiksa.
            if (is_int($key)) {
                $out[$key] = $this->value($value, $depth);

                continue;
            }

            $name = strtolower((string) $key);

            if ($this->matches($name, 'deny_keys', 'deny_keys_exact')) {
                $out[$key] = self::REDACTED;

                continue;
            }

            if ($this->matches($name, 'pseudonymize_keys', 'pseudonymize_keys_exact')) {
                foreach ($this->pseudonymized((string) $key, $name, $value) as $k => $v) {
                    $out[$k] = $v;
                }

                continue;
            }

            if ($this->matches($name, 'summarize_keys')) {
                $out[$key] = $this->summarize($value);

                continue;
            }

            $out[$key] = $this->value($value, $depth);
        }

        return $out;
    }

    /**
     * Kunci identitas -> penanda saja. Kunci aslinya DIHAPUS, diganti
     * `<kunci>_sha`, supaya sebuah nilai asli tidak pernah bisa lolos hanya
     * karena tipenya tidak terduga.
     *
     * @return array<string, mixed>
     */
    private function pseudonymized(string $original, string $lowerName, mixed $value): array
    {
        if (is_array($value)) {
            return [$original => $this->summarize($value)];
        }

        if (! is_string($value) && ! is_int($value)) {
            return [$original => $value === null ? null : self::REDACTED];
        }

        $string = (string) $value;

        if ($string === '') {
            return [$original.'_sha' => null];
        }

        if (str_contains($lowerName, 'email')) {
            $parts = $this->email($string);

            return array_filter([
                $original.'_sha' => $parts['sha'],
                $original.'_domain' => $parts['domain'] ?? null,
            ], static fn (mixed $v): bool => $v !== null);
        }

        return [$original.'_sha' => $this->pseudonym($string)];
    }

    /** Bentuk dan ukuran dipertahankan, isinya tidak. */
    private function summarize(mixed $value): string
    {
        return match (true) {
            is_string($value) => '[len:'.mb_strlen($value).']',
            is_array($value) => '[items:'.count($value).']',
            $value === null => '[null]',
            is_bool($value) => '[bool]',
            is_int($value), is_float($value) => '[num]',
            default => self::REDACTED,
        };
    }

    private function value(mixed $value, int $depth): mixed
    {
        return match (true) {
            is_array($value) => $this->walk($value, $depth + 1),
            is_string($value) => $this->text($value),
            is_int($value), is_float($value), is_bool($value), $value === null => $value,

            // Objek tidak pernah diserialisasi: `__toString()` atau properti
            // publiknya bisa berisi apa saja, termasuk seluruh model pengguna.
            is_object($value) => '[object:'.$value::class.']',
            default => '['.gettype($value).']',
        };
    }

    private function matches(string $lowerName, string $substringKey, ?string $exactKey = null): bool
    {
        if ($exactKey !== null) {
            /** @var array<int, string> $exact */
            $exact = (array) $this->config->get('axiom.privacy.'.$exactKey, []);

            foreach ($exact as $candidate) {
                if ($lowerName === strtolower($candidate)) {
                    return true;
                }
            }
        }

        /** @var array<int, string> $needles */
        $needles = (array) $this->config->get('axiom.privacy.'.$substringKey, []);

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($lowerName, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Penyaring pola nilai.
     *
     * Urutannya penting: yang paling spesifik lebih dulu, supaya kunci PEM
     * tidak lebih dulu tercabik oleh aturan base64 yang lebih umum.
     */
    private function scrub(string $value): string
    {
        static $patterns = [
            // Kunci privat — harus paling awal, isinya base64 multi-baris.
            '/-----BEGIN[^-]{0,40}PRIVATE KEY-----.*?-----END[^-]{0,40}-----/s',

            // Header otorisasi yang ikut terbawa ke dalam sebuah string.
            '/\bBearer\s+[A-Za-z0-9._\-|+\/=]{8,}/i',
            '/\bBasic\s+[A-Za-z0-9+\/=]{8,}/i',

            // Token Sanctum (`<id>|<40+ karakter>`) dan JWT.
            '/\b\d+\|[A-Za-z0-9]{20,}\b/',
            '/\beyJ[A-Za-z0-9_\-]{6,}\.[A-Za-z0-9_\-]{6,}\.[A-Za-z0-9_\-]{6,}\b/',

            // APP_KEY / kunci terenkode Laravel.
            '/\bbase64:[A-Za-z0-9+\/=]{16,}/',

            // Rahasia yang menempel di query string sebuah URL yang dicatat.
            '/([?&](?:password|passwd|token|secret|api_?key|code|signature)=)[^&\s]+/i',

            // Kredensial di dalam URL: scheme://user:pass@host
            '/(\/\/)[^\/\s:@]{1,64}:[^\/\s@]{1,64}@/',

            // Identitas.
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,24}/',
            '/(?:\+62|\b62|\b0)8\d{2}[\s\-]?\d{3,4}[\s\-]?\d{3,5}\b/',

            // NIK dan nomor kartu: 16 angka berurutan.
            '/\b\d{16}\b/',

            // Blob panjang: hash mentah, dump base64, kunci API tak dikenal.
            '/\b[A-Fa-f0-9]{40,}\b/',
            '/\b[A-Za-z0-9+\/]{60,}={0,2}\b/',
        ];

        $result = preg_replace($patterns, self::SCRUBBED, $value);

        // preg_replace mengembalikan null saat pola gagal (mis. batas backtrack
        // terlampaui pada string patologis). Kegagalan harus jatuh ke sisi
        // aman: buang nilainya, bukan kirim yang belum tersaring.
        return $result ?? self::REDACTED;
    }

    private function truncate(string $value, ?int $maxLength = null): string
    {
        $max = $maxLength ?? (int) $this->config->get('axiom.limits.max_string', 512);

        if ($max <= 0 || mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max).'…[+'.(mb_strlen($value) - $max).']';
    }

    /** /24 untuk IPv4, /48 untuk IPv6. */
    private function ipPrefix(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $parts = explode('.', $ip);

            return $parts[0].'.'.$parts[1].'.'.$parts[2].'.0/24';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            // Alamat yang sudah lolos filter_var selalu bisa di-pack, tapi
            // hasil false tetap dijaga di sini supaya kegagalan tak terduga
            // menghasilkan "tidak ada info IP", bukan galat.
            $packed = inet_pton($ip);
            $prefix = $packed === false ? false : inet_ntop(substr($packed, 0, 6).str_repeat("\0", 10));

            return $prefix === false ? null : $prefix.'/48';
        }

        return null;
    }

    private function boolConfig(string $key, bool $default): bool
    {
        return (bool) $this->config->get($key, $default);
    }
}
