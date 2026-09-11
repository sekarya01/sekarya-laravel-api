<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\Axiom\Redactor;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Redactor adalah satu-satunya pintu keluar data ke Axiom, jadi kelas test ini
 * adalah kontrol keamanan — bukan sekadar test unit.
 *
 * Setiap test di sini menegaskan satu bentuk kebocoran yang MUNGKIN TERJADI di
 * aplikasi ini, dengan nilai berbentuk asli (token Sanctum benar-benar
 * `<id>|<40 karakter>`, NIK benar-benar 16 angka), karena penyaring pola hanya
 * teruji oleh bentuk yang asli.
 */
final class RedactorTest extends TestCase
{
    private function redactor(): Redactor
    {
        return app(Redactor::class);
    }

    // ── Lapis 1: nama kunci ──────────────────────────────────────────────────

    #[DataProvider('deniedKeys')]
    public function test_it_never_emits_a_denied_key(string $key): void
    {
        $out = $this->redactor()->payload([$key => 'nilai-rahasia-asli']);

        $this->assertSame(Redactor::REDACTED, $out[$key]);
        $this->assertStringNotContainsString('nilai-rahasia-asli', json_encode($out));
    }

    /** @return array<int, array<int, string>> */
    public static function deniedKeys(): array
    {
        return array_map(static fn (string $k): array => [$k], [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'remember_token',
            'access_token',
            'plainTextToken',
            'authorization',
            'cookie',
            'api_key',
            'secret',
            'signature',
            'document_number',
            'document_number_enc',
            'account_number',
            'npwp',
            'nik',
            'cvv',
            'code_hash',
            'verification_code',
            'otp',
            'gateway_payload',
            'id_card_photo_path',
            'selfie_photo_path',
        ]);
    }

    /**
     * `code` di body request adalah kode verifikasi enam angka — sebuah
     * kredensial yang bisa mengaktifkan akun orang lain.
     */
    public function test_the_bare_code_key_is_treated_as_a_credential(): void
    {
        $out = $this->redactor()->payload(['email' => 'a@b.test', 'code' => '623862']);

        $this->assertSame(Redactor::REDACTED, $out['code']);
        $this->assertStringNotContainsString('623862', json_encode($out));
    }

    /**
     * Sisi lain dari aturan di atas: kunci yang HANYA mirip tidak boleh ikut
     * terbuang, karena justru itu metadata yang dipakai saat debugging.
     */
    public function test_it_keeps_lookalike_keys_that_carry_no_secret(): void
    {
        $out = $this->redactor()->payload([
            'domain_code' => 'invalid_credentials',
            'status_code' => 422,
            'route_name' => 'v1.auth.login',
            'queue_name' => 'default',
            'filename' => 'report.csv',
        ]);

        $this->assertSame('invalid_credentials', $out['domain_code']);
        $this->assertSame(422, $out['status_code']);
        $this->assertSame('v1.auth.login', $out['route_name']);
        $this->assertSame('default', $out['queue_name']);
        $this->assertSame('report.csv', $out['filename']);
    }

    public function test_denied_keys_are_caught_at_any_depth(): void
    {
        $out = $this->redactor()->payload([
            'user' => ['profile' => ['password' => 'RahasiaKuat2026']],
        ]);

        $this->assertStringNotContainsString('RahasiaKuat2026', json_encode($out));
    }

    public function test_an_array_under_a_denied_key_is_redacted_whole(): void
    {
        $out = $this->redactor()->payload([
            'gateway_payload' => ['va' => '8808123456789012', 'nested' => ['x' => 'y']],
        ]);

        $this->assertSame(Redactor::REDACTED, $out['gateway_payload']);
    }

    // ── Pseudonimisasi ──────────────────────────────────────────────────────

    public function test_email_becomes_a_pseudonym_plus_domain(): void
    {
        $out = $this->redactor()->payload(['email' => 'Budi.Prasetyo@Contoh.test']);

        $this->assertArrayNotHasKey('email', $out);
        $this->assertSame('contoh.test', $out['email_domain']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $out['email_sha']);
        $this->assertStringNotContainsString('budi.prasetyo', strtolower((string) json_encode($out)));
    }

    public function test_the_pseudonym_is_stable_so_events_can_be_correlated(): void
    {
        $a = $this->redactor()->pseudonym('budi@contoh.test');
        $b = $this->redactor()->pseudonym('  BUDI@CONTOH.TEST  ');

        $this->assertSame($a, $b, 'normalisasi harus membuat orang yang sama menghasilkan penanda yang sama');
        $this->assertNotSame($a, $this->redactor()->pseudonym('siti@contoh.test'));
    }

    /**
     * Ruang tebakan sebuah alamat e-mail kecil, jadi SHA256 telanjang bisa
     * dibalik dengan kamus. Kuncinya harus APP_KEY, yang tidak ada di Axiom.
     */
    public function test_the_pseudonym_is_keyed_not_a_plain_hash(): void
    {
        $value = 'budi@contoh.test';

        $this->assertNotSame(
            substr(hash('sha256', $value), 0, 16),
            $this->redactor()->pseudonym($value),
        );
    }

    /** Tanpa kunci, penanda tak berkunci hanya memberi rasa aman palsu. */
    public function test_without_an_app_key_it_emits_nothing_rather_than_a_weak_hash(): void
    {
        config()->set('app.key', '');

        $this->assertSame(Redactor::REDACTED, $this->redactor()->pseudonym('budi@contoh.test'));
    }

    public function test_a_person_name_is_pseudonymized_but_a_route_name_is_not(): void
    {
        $out = $this->redactor()->payload(['name' => 'Budi Prasetyo', 'name_of_queue' => 'high']);

        $this->assertArrayNotHasKey('name', $out);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $out['name_sha']);
        $this->assertSame('high', $out['name_of_queue']);
    }

    public function test_identity_fields_are_pseudonymized(): void
    {
        $out = $this->redactor()->payload([
            'first_name' => 'Budi',
            'last_name' => 'Prasetyo',
            'username' => 'budi.prasetyo',
            'display_name' => 'Budi Tukang AC',
        ]);

        foreach (['first_name', 'last_name', 'username', 'display_name'] as $key) {
            $this->assertArrayNotHasKey($key, $out);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $out[$key.'_sha']);
        }
    }

    public function test_pseudonymize_can_be_turned_off_entirely(): void
    {
        config()->set('axiom.privacy.pseudonymize', false);

        $out = $this->redactor()->payload(['email' => 'budi@contoh.test']);

        $this->assertSame(Redactor::REDACTED, $out['email_sha']);
    }

    // ── Ringkasan teks bebas ────────────────────────────────────────────────

    public function test_free_text_keeps_its_length_and_loses_its_content(): void
    {
        $out = $this->redactor()->payload([
            'bio' => 'Tukang listrik 10 tahun',
            'description' => null,
            'title' => ['a', 'b'],
        ]);

        $this->assertSame('[len:23]', $out['bio']);
        $this->assertSame('[null]', $out['description']);
        $this->assertSame('[items:2]', $out['title']);
    }

    // ── Lapis 2: pola nilai ─────────────────────────────────────────────────

    #[DataProvider('secretValues')]
    public function test_it_scrubs_a_secret_found_under_an_unexpected_key(string $secret, string $needle): void
    {
        // Kunci yang tidak ada di daftar mana pun — inilah yang membuat lapis 2
        // diperlukan: skema payload berubah, denylist tertinggal.
        $out = $this->redactor()->payload(['catatan_baru' => 'nilainya: '.$secret]);

        $this->assertStringNotContainsString($needle, (string) json_encode($out));
    }

    /** @return array<string, array<int, string>> */
    public static function secretValues(): array
    {
        return [
            'token sanctum' => ['42|kZ3nQm8vXpL2rT7yW1bC5dF9gH0jK4mN6pQ8sU3x', 'kZ3nQm8vXpL2rT7yW1bC5dF9gH0jK4mN6pQ8sU3x'],
            'header bearer' => ['Bearer kZ3nQm8vXpL2rT7yW1bC5dF9gH0jK4mN', 'kZ3nQm8vXpL2rT7yW1bC5dF9gH0jK4mN'],
            'header basic' => ['Basic YWRtaW46cGFzc3dvcmQxMjM=', 'YWRtaW46cGFzc3dvcmQxMjM='],
            'jwt' => ['eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NSJ9.dBjftJeZ4CVPmB92K27uhbUJU1p1r_wW1g', 'dBjftJeZ4CVPmB92K27uhbUJU1p1r_wW1g'],
            // Kunci PALSU, dan harus tetap begitu. Berkas test ikut
            // ter-commit, dan kunci yang pernah masuk riwayat git harus
            // dianggap bocor selamanya — memutarnya jauh lebih mahal
            // daripada mengetik nilai palsu di sini.
            'app key' => ['base64:AAAAcontohKUNCIpalsuUNTUKtestSAJAbukanASLI==', 'AAAAcontohKUNCIpalsuUNTUKtestSAJAbukanASLI=='],
            'alamat email' => ['hubungi budi.prasetyo@contoh.test ya', 'budi.prasetyo@contoh.test'],
            'nomor hp' => ['telepon 081112223333 sekarang', '081112223333'],
            'nomor hp +62' => ['telepon +628111222333 sekarang', '+628111222333'],
            'nik 16 angka' => ['NIK 3271012345678901 milik dia', '3271012345678901'],
            'kredensial di url' => ['https://admin:rahasia123@mitra.test/cb', 'admin:rahasia123@'],
            'rahasia di query' => ['https://mitra.test/cb?token=abc123secret&id=7', 'abc123secret'],
            'blob hex panjang' => [str_repeat('a1b2c3d4', 8), str_repeat('a1b2c3d4', 8)],
        ];
    }

    public function test_it_scrubs_a_private_key_block(): void
    {
        $pem = "-----BEGIN RSA PRIVATE KEY-----\nMIIEowIBAAKCAQEA\n-----END RSA PRIVATE KEY-----";

        $out = $this->redactor()->text($pem);

        $this->assertStringNotContainsString('MIIEowIBAAKCAQEA', $out);
    }

    /**
     * Pesan exception adalah tempat kebocoran paling sering terjadi tanpa
     * sengaja: galat unique constraint MySQL memuat nilai yang bentrok, dan di
     * tabel `users` nilai itu adalah alamat e-mail.
     */
    public function test_it_scrubs_an_email_out_of_a_database_error_message(): void
    {
        $message = "SQLSTATE[23000]: Duplicate entry 'budi@contoh.test' for key 'users_email_unique'";

        $out = $this->redactor()->text($message);

        $this->assertStringNotContainsString('budi@contoh.test', $out);
        $this->assertStringContainsString('users_email_unique', $out, 'nama indeks harus tetap ada — itu yang menjelaskan galatnya');
    }

    public function test_value_scrubbing_can_be_turned_off_for_a_trusted_environment(): void
    {
        config()->set('axiom.privacy.scrub_value_patterns', false);

        $this->assertSame('budi@contoh.test', $this->redactor()->text('budi@contoh.test'));
    }

    /** Penanda pseudonim (16 hex) tidak boleh ikut tercabik oleh aturan blob. */
    public function test_scrubbing_does_not_destroy_the_pseudonyms_it_just_created(): void
    {
        $out = $this->redactor()->payload(['email' => 'budi@contoh.test']);
        $scrubbed = $this->redactor()->scrubDeep($out);

        $this->assertSame($out['email_sha'], $scrubbed['email_sha']);
    }

    /** ULID adalah id internal, bukan rahasia — ia harus tetap terbaca. */
    public function test_scrubbing_keeps_ulids_intact(): void
    {
        $ulid = '01JBQ7XK2M3N4P5Q6R7S8T9V0W';

        $this->assertSame($ulid, $this->redactor()->text($ulid));
    }

    // ── Lapis 3: batas ukuran ───────────────────────────────────────────────

    public function test_long_strings_are_truncated_with_the_original_length_kept(): void
    {
        config()->set('axiom.limits.max_string', 10);

        $out = $this->redactor()->text(str_repeat('x', 25));

        $this->assertSame(str_repeat('x', 10).'…[+15]', $out);
    }

    public function test_deep_structures_stop_at_the_configured_depth(): void
    {
        config()->set('axiom.limits.max_depth', 2);

        $out = $this->redactor()->payload(['a' => ['b' => ['c' => ['d' => 'terlalu dalam']]]]);

        $this->assertStringNotContainsString('terlalu dalam', (string) json_encode($out));
        $this->assertStringContainsString('max_depth', (string) json_encode($out));
    }

    public function test_big_arrays_are_cut_and_the_cut_is_reported(): void
    {
        config()->set('axiom.limits.max_array_items', 3);

        $out = $this->redactor()->payload(array_fill_keys(['a', 'b', 'c', 'd', 'e'], 1));

        $this->assertSame('max_array_items:5', $out['_truncated']);
    }

    /**
     * Objek tidak pernah diserialisasi: `__toString()` atau properti publiknya
     * bisa berisi apa saja, termasuk seluruh model pengguna.
     */
    public function test_objects_are_reduced_to_their_class_name(): void
    {
        $out = $this->redactor()->payload(['e' => new RuntimeException('rahasia')]);

        $this->assertSame('[object:RuntimeException]', $out['e']);
    }

    public function test_scalars_keep_their_type(): void
    {
        $out = $this->redactor()->payload(['i' => 7, 'f' => 1.5, 'b' => true, 'n' => null]);

        $this->assertSame(7, $out['i']);
        $this->assertSame(1.5, $out['f']);
        $this->assertTrue($out['b']);
        $this->assertNull($out['n']);
    }

    public function test_list_indexes_are_preserved_and_their_values_still_filtered(): void
    {
        $out = $this->redactor()->payload(['tags' => ['budi@contoh.test', 'aman']]);

        $this->assertStringNotContainsString('budi@contoh.test', (string) json_encode($out));
        $this->assertSame('aman', $out['tags'][1]);
    }

    // ── Header: allowlist ───────────────────────────────────────────────────

    public function test_headers_use_an_allowlist_so_credentials_can_never_pass(): void
    {
        $out = $this->redactor()->headers([
            'authorization' => ['Bearer 42|kZ3nQm8vXpL2rT7yW1bC5dF9gH0jK4mN'],
            'cookie' => ['laravel_session=abc'],
            'x-api-key' => ['rahasia'],
            'x-csrf-token' => ['abc'],
            'user-agent' => ['Sekarya/1.2 (Android 14)'],
            'content-type' => ['application/json'],
        ]);

        $this->assertSame(['user-agent', 'content-type'], array_keys($out));
        $this->assertSame('Sekarya/1.2 (Android 14)', $out['user-agent']);
    }

    public function test_header_values_are_still_scrubbed_after_passing_the_allowlist(): void
    {
        // Referer ada di allowlist, dan sebuah referer bisa memuat query string
        // berisi token — allowlist saja tidak cukup.
        $out = $this->redactor()->headers([
            'referer' => ['https://app.sekarya.id/reset?token=abc123secret'],
        ]);

        $this->assertStringNotContainsString('abc123secret', $out['referer']);
    }

    // ── IP ──────────────────────────────────────────────────────────────────

    public function test_ip_defaults_to_prefix_plus_hash(): void
    {
        $out = $this->redactor()->ip('203.0.113.42');

        $this->assertSame('203.0.113.0/24', $out['ip_prefix']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $out['ip_sha']);
        $this->assertArrayNotHasKey('ip', $out);
    }

    public function test_ipv6_is_reduced_to_a_48_prefix(): void
    {
        $out = $this->redactor()->ip('2001:db8:1234:5678::1');

        $this->assertSame('2001:db8:1234::/48', $out['ip_prefix']);
    }

    #[DataProvider('ipModes')]
    public function test_ip_modes(string $mode, array $expectedKeys): void
    {
        config()->set('axiom.privacy.ip_mode', $mode);

        $this->assertSame($expectedKeys, array_keys($this->redactor()->ip('203.0.113.42')));
    }

    /** @return array<string, array{0: string, 1: array<int, string>}> */
    public static function ipModes(): array
    {
        return [
            'full' => ['full', ['ip']],
            'hash' => ['hash', ['ip_sha']],
            'prefix' => ['prefix', ['ip_prefix']],
            'none' => ['none', []],
            'prefix_hash' => ['prefix_hash', ['ip_prefix', 'ip_sha']],
        ];
    }

    public function test_a_missing_or_malformed_ip_yields_nothing(): void
    {
        $this->assertSame([], $this->redactor()->ip(null));
        $this->assertSame([], $this->redactor()->ip(''));
        $this->assertArrayNotHasKey('ip_prefix', $this->redactor()->ip('bukan-ip'));
    }

    // ── Stack trace ─────────────────────────────────────────────────────────

    /**
     * `getTraceAsString()` menyertakan argumen skalar, sehingga sebuah kata
     * sandi yang dilempar ke `Hash::check('rahasia', …)` muncul apa adanya di
     * dalam trace. Karena itu trace dibangun ulang tanpa pernah membaca `args`.
     */
    public function test_the_trace_never_contains_call_arguments(): void
    {
        $frames = [[
            'file' => base_path('app/Actions/Auth/LoginAction.php'),
            'line' => 42,
            'class' => 'Illuminate\Hashing\BcryptHasher',
            'type' => '->',
            'function' => 'check',
            'args' => ['RahasiaKuat2026', '$2y$12$abcdef'],
        ]];

        $out = $this->redactor()->trace($frames);

        $this->assertSame(
            ['app/Actions/Auth/LoginAction.php:42 Illuminate\Hashing\BcryptHasher->check()'],
            $out,
        );
        $this->assertStringNotContainsString('RahasiaKuat2026', (string) json_encode($out));
    }

    /** Path absolut membocorkan struktur direktori dan nama pengguna sistem. */
    public function test_trace_paths_are_relative_to_the_project(): void
    {
        $out = $this->redactor()->trace([[
            'file' => base_path('app/X.php'), 'line' => 1, 'function' => 'f',
        ]]);

        $this->assertStringStartsWith('app/X.php:1', $out[0]);
        $this->assertStringNotContainsString(base_path(), $out[0]);
    }

    public function test_the_trace_is_capped(): void
    {
        config()->set('axiom.limits.max_trace_frames', 2);

        $frames = array_fill(0, 10, ['file' => 'x.php', 'line' => 1, 'function' => 'f']);

        $this->assertCount(2, $this->redactor()->trace($frames));
    }

    public function test_a_frame_without_a_file_is_still_reported(): void
    {
        $out = $this->redactor()->trace([['function' => 'call_user_func']]);

        $this->assertSame('[internal]:0 call_user_func()', $out[0]);
    }

    // ── scrubDeep: jaring terakhir ──────────────────────────────────────────

    /**
     * scrubDeep sengaja TIDAK menerapkan aturan nama kunci. Kalau ia
     * menerapkannya, `job.name` akan dipseudonimkan seperti nama orang dan
     * event job menjadi tidak terbaca.
     */
    public function test_scrub_deep_leaves_metadata_keys_alone(): void
    {
        $out = $this->redactor()->scrubDeep(['job' => ['name' => 'App\Jobs\SendMail', 'attempts' => 2]]);

        $this->assertSame('App\Jobs\SendMail', $out['job']['name']);
        $this->assertSame(2, $out['job']['attempts']);
    }

    public function test_scrub_deep_still_catches_a_secret_a_listener_forgot(): void
    {
        $out = $this->redactor()->scrubDeep([
            'job' => ['name' => 'X', 'payload' => 'user budi@contoh.test token 42|kZ3nQm8vXpL2rT7yW1bC5dF9gH0jK4mN'],
        ]);

        $json = (string) json_encode($out);

        $this->assertStringNotContainsString('budi@contoh.test', $json);
        $this->assertStringNotContainsString('kZ3nQm8vXpL2rT7yW1bC5dF9gH0jK4mN', $json);
    }

    public function test_scrub_deep_reduces_a_resource_to_its_type(): void
    {
        $handle = fopen('php://memory', 'r');

        $out = $this->redactor()->scrubDeep(['stream' => $handle]);

        fclose($handle);

        $this->assertStringContainsString('resource', $out['stream']);
    }

    public function test_scrub_deep_reduces_objects_and_stops_at_depth(): void
    {
        config()->set('axiom.limits.max_depth', 1);

        $out = $this->redactor()->scrubDeep([
            'o' => new RuntimeException('x'),
            'a' => ['b' => ['c' => ['d' => 'dalam']]],
        ]);

        $this->assertSame('[object:RuntimeException]', $out['o']);
        $this->assertStringContainsString('max_depth', (string) json_encode($out));
    }
}
