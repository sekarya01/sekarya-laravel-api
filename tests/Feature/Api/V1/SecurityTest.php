<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserStatus;
use App\Support\TokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        RateLimiter::clear('login');
    }

    // ── setiap rute terlindungi ─────────────────────────────────────────────

    /**
     * Audit menyeluruh: tidak boleh ada rute aplikasi yang kehilangan auth,
     * pengecekan jenis token, atau rate limit.
     */
    public function test_every_app_route_has_all_three_protection_layers(): void
    {
        // Satu-satunya rute yang boleh tanpa autentikasi: pintu masuk.
        // `admin/auth/login` ada di sini untuk alasan yang sama seperti
        // `auth/login` — belum ada token yang bisa dibawa.
        $expectedWithoutAuth = [
            'api/v1/auth/register',
            'api/v1/auth/verify-email',
            'api/v1/auth/resend-code',
            'api/v1/auth/login',
            'api/v1/admin/auth/login',
        ];

        $withoutAuth = [];
        $withoutAbility = [];
        $withoutThrottle = [];
        $adminWithoutActiveCheck = [];

        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1/')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $has = fn (string $needle): bool => (bool) array_filter(
                $middleware,
                fn ($m) => is_string($m) && str_contains($m, $needle),
            );

            // Dua guard, dua populasi pemilik token. Rute pengelola memakai
            // `auth:admin`, yang provider-nya `admins` — dan itulah yang
            // membuat token pengguna ditolak di sana.
            if (! $has('auth:sanctum') && ! $has('auth:admin')) {
                $withoutAuth[] = $uri;

                continue;
            }
            if (! $has('abilities:')) {
                $withoutAbility[] = $uri;
            }
            if (! $has('throttle:')) {
                $withoutThrottle[] = $uri;
            }

            // Lapis keempat, hanya untuk pengelola: status akun diperiksa per
            // permintaan. Tanpa ini, pencabutan kewenangan baru berlaku
            // delapan jam kemudian — selama token akses yang lama masih hidup.
            if ($has('auth:admin') && ! $has('admin.active')) {
                $adminWithoutActiveCheck[] = $uri;
            }
        }

        $this->assertEqualsCanonicalizing($expectedWithoutAuth, array_unique($withoutAuth));
        // logout menerima kedua jenis token dengan sengaja.
        $this->assertEqualsCanonicalizing(
            ['api/v1/auth/logout', 'api/v1/admin/auth/logout'],
            array_values(array_unique($withoutAbility)),
        );
        $this->assertSame([], array_unique($withoutThrottle));

        // Dua pengecualian yang disengaja, dan keduanya punya alasan:
        //  - logout: pengelola yang baru dinonaktifkan harus tetap bisa
        //    mencabut tokennya sendiri.
        //  - refresh: statusnya diperiksa di dalam RefreshAdminTokenAction,
        //    karena di situlah keputusan "boleh diperpanjang" diambil.
        $this->assertEqualsCanonicalizing(
            ['api/v1/admin/auth/logout', 'api/v1/admin/auth/refresh'],
            array_values(array_unique($adminWithoutActiveCheck)),
        );
    }

    // ── pemisahan jenis token ───────────────────────────────────────────────

    public function test_no_token_is_unauthenticated(): void
    {
        $this->getJson(route('v1.me.show'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_a_bogus_token_is_unauthenticated(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer 999|palsu', 'Accept' => 'application/json'])
            ->getJson(route('v1.me.show'))
            ->assertUnauthorized();
    }

    public function test_the_long_lived_token_cannot_call_app_endpoints(): void
    {
        $user = $this->activeUser();

        $this->asUserWithLongLived($user)->getJson(route('v1.me.show'))->assertForbidden();
        $this->asUserWithLongLived($user)->getJson(route('v1.tasks.index'))->assertForbidden();
        $this->asUserWithLongLived($user)->getJson(route('v1.categories.index'))->assertForbidden();
    }

    public function test_the_access_token_cannot_refresh(): void
    {
        $this->asUser($this->activeUser())->postJson(route('v1.auth.refresh'))->assertForbidden();
    }

    public function test_refreshing_kills_the_previous_access_token(): void
    {
        $user = $this->activeUser();
        $pair = app(TokenIssuer::class)->issuePair($user);
        $old = $pair['access']->plainTextToken;

        $this->app['auth']->forgetGuards();
        $new = $this->withHeaders([
            'Authorization' => 'Bearer '.$pair['long_lived']->plainTextToken,
            'Accept' => 'application/json',
        ])->postJson(route('v1.auth.refresh'))->assertOk()->json('data.access_token');

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$old, 'Accept' => 'application/json'])
            ->getJson(route('v1.me.show'))
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$new, 'Accept' => 'application/json'])
            ->getJson(route('v1.me.show'))
            ->assertOk();
    }

    public function test_an_expired_access_token_is_rejected(): void
    {
        $user = $this->activeUser();
        $pair = app(TokenIssuer::class)->issuePair($user);
        // Dilewatkan memakai jam PHP, bukan NOW() SQL.
        $pair['access']->accessToken->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->app['auth']->forgetGuards();
        $this->withHeaders([
            'Authorization' => 'Bearer '.$pair['access']->plainTextToken,
            'Accept' => 'application/json',
        ])->getJson(route('v1.me.show'))->assertUnauthorized();
    }

    public function test_a_suspended_user_cannot_refresh(): void
    {
        $user = $this->activeUser();
        $pair = app(TokenIssuer::class)->issuePair($user);
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->app['auth']->forgetGuards();
        $this->withHeaders([
            'Authorization' => 'Bearer '.$pair['long_lived']->plainTextToken,
            'Accept' => 'application/json',
        ])->postJson(route('v1.auth.refresh'))
            ->assertForbidden()
            ->assertJsonPath('code', 'account_not_active');
    }

    // ── rate limit ──────────────────────────────────────────────────────────

    public function test_login_is_rate_limited(): void
    {
        $limit = (int) config('sekarya.rate_limits.login');
        $payload = ['email' => 'hantu@sekarya.test', 'password' => 'salah'];

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson(route('v1.auth.login'), $payload)->assertUnauthorized();
        }

        $this->postJson(route('v1.auth.login'), $payload)->assertStatus(429);
    }

    public function test_rate_limit_headers_are_present(): void
    {
        $this->postJson(route('v1.auth.login'), ['email' => 'x@sekarya.test', 'password' => 'y'])
            ->assertHeader('X-RateLimit-Limit')
            ->assertHeader('X-RateLimit-Remaining');
    }

    /** Kunci login adalah email+IP, jadi email lain tidak ikut terkunci. */
    public function test_login_limit_is_scoped_per_identity(): void
    {
        $limit = (int) config('sekarya.rate_limits.login');

        for ($i = 0; $i < $limit + 1; $i++) {
            $this->postJson(route('v1.auth.login'), ['email' => 'korban@sekarya.test', 'password' => 'salah']);
        }

        // Email berbeda masih boleh mencoba.
        $this->postJson(route('v1.auth.login'), ['email' => 'orang-lain@sekarya.test', 'password' => 'salah'])
            ->assertUnauthorized();
    }

    public function test_register_is_rate_limited(): void
    {
        $limit = (int) config('sekarya.rate_limits.register');

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson(route('v1.auth.register'), [
                'first_name' => "T{$i}",
                'email' => "t{$i}@sekarya.test",
                'phone' => '+62811100'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'password' => 'RahasiaKuat2026',
                'password_confirmation' => 'RahasiaKuat2026',
            ])->assertAccepted();
        }

        $this->postJson(route('v1.auth.register'), [
            'first_name' => 'Lebih',
            'email' => 'lebih@sekarya.test',
            'phone' => '+628111009999',
            'password' => 'RahasiaKuat2026',
            'password_confirmation' => 'RahasiaKuat2026',
        ])->assertStatus(429);
    }

    // ── CORS ────────────────────────────────────────────────────────────────

    public function test_a_listed_origin_gets_cors_headers(): void
    {
        $origin = config('cors.allowed_origins')[0];

        $this->call('OPTIONS', '/api/v1/me', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    public function test_an_unlisted_origin_gets_no_cors_headers(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://evil.example', 'Accept' => 'application/json'])
            ->getJson(route('v1.categories.index'));

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_credentials_are_not_supported(): void
    {
        $this->assertFalse(config('cors.supports_credentials'));
    }

    public function test_rate_limit_headers_are_exposed_to_the_browser(): void
    {
        $exposed = config('cors.exposed_headers');

        $this->assertContains('X-RateLimit-Limit', $exposed);
        $this->assertContains('X-RateLimit-Remaining', $exposed);
        $this->assertContains('Retry-After', $exposed);
    }

    public function test_cors_never_uses_a_wildcard_origin(): void
    {
        $this->assertNotContains('*', config('cors.allowed_origins'));
    }

    // ── batas pengungkapan ──────────────────────────────────────────────────

    public function test_another_users_public_profile_hides_contact_details(): void
    {
        $poster = $this->activeUser(['email' => 'poster@sekarya.test', 'phone' => '+628111000111']);
        $worker = $this->activeUser();

        $task = $this->asUser($poster)->postJson(route('v1.tasks.store'), [
            'category_id' => $this->anyCategory()->getKey(),
            'title' => 'Cuci AC',
            'description' => 'Servis.',
            'budget_min' => 150_000,
            'publish_now' => true,
        ])->assertCreated()->json('data.id');

        $body = $this->asUser($worker)->getJson(route('v1.tasks.show', $task))->assertOk();

        $this->assertNull($body->json('data.poster.email'));
        $this->assertNull($body->json('data.poster.phone'));
        $this->assertStringNotContainsString('poster@sekarya.test', $body->getContent());
        $this->assertStringNotContainsString('+628111000111', $body->getContent());
        $this->assertNotNull($body->json('data.poster.as_poster'));
        $this->assertNotNull($body->json('data.poster.identity_verified'));
    }

    public function test_gateway_payload_is_never_exposed(): void
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();

        $task = $this->asUser($poster)->postJson(route('v1.tasks.store'), [
            'category_id' => $this->anyCategory()->getKey(),
            'title' => 'Cuci AC',
            'description' => 'Servis.',
            'budget_min' => 150_000,
            'publish_now' => true,
        ])->json('data.id');

        $bid = $this->asUser($worker)
            ->postJson(route('v1.tasks.bids.store', $task), ['amount' => 200_000])
            ->json('data.id');
        $this->asUser($poster)->postJson(route('v1.bids.accept', $bid))->assertOk();

        $body = $this->asUser($poster)->getJson(route('v1.tasks.payment.show', $task))->getContent();

        $this->assertStringNotContainsString('gateway_payload', $body);
    }
}
