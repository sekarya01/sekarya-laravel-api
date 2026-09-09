<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\TokenAbility;
use App\Support\TokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TokenIssuerTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_pair_creates_exactly_two_tokens(): void
    {
        $user = $this->activeUser();

        app(TokenIssuer::class)->issuePair($user);

        $this->assertSame(2, $user->tokens()->count());
        $this->assertSame(1, $user->tokens()->where('name', 'access')->count());
        $this->assertSame(1, $user->tokens()->where('name', 'long_lived')->count());
    }

    public function test_the_two_tokens_have_disjoint_abilities(): void
    {
        $user = $this->activeUser();

        $pair = app(TokenIssuer::class)->issuePair($user);

        $this->assertSame([TokenAbility::Access->value], $pair['access']->accessToken->abilities);
        $this->assertSame([TokenAbility::Refresh->value], $pair['long_lived']->accessToken->abilities);
        $this->assertNotContains(
            TokenAbility::Access->value,
            $pair['long_lived']->accessToken->abilities,
            'long_lived tidak boleh punya kemampuan access',
        );
    }

    public function test_expiries_follow_the_configuration(): void
    {
        $user = $this->activeUser();

        $pair = app(TokenIssuer::class)->issuePair($user);

        $accessHours = (int) round(
            $pair['access']->accessToken->created_at
                ->diffInHours($pair['access']->accessToken->expires_at),
        );
        $longDays = (int) round(
            $pair['long_lived']->accessToken->created_at
                ->diffInDays($pair['long_lived']->accessToken->expires_at),
        );

        $this->assertSame((int) config('sekarya.tokens.access_ttl_hours'), $accessHours);
        $this->assertSame((int) config('sekarya.tokens.long_lived_ttl_days'), $longDays);
    }

    public function test_issue_pair_revokes_everything_first(): void
    {
        $user = $this->activeUser();
        $user->createToken('lama', ['*']);
        app(TokenIssuer::class)->issuePair($user);

        app(TokenIssuer::class)->issuePair($user);

        $this->assertSame(2, $user->tokens()->count());
        $this->assertSame(0, $user->tokens()->where('name', 'lama')->count());
    }

    public function test_rotate_access_replaces_only_the_access_token(): void
    {
        $user = $this->activeUser();
        $pair = app(TokenIssuer::class)->issuePair($user);
        $oldAccessId = $pair['access']->accessToken->getKey();
        $longId = $pair['long_lived']->accessToken->getKey();

        $new = app(TokenIssuer::class)->rotateAccess($user);

        $this->assertNull($user->tokens()->find($oldAccessId));
        $this->assertNotNull($user->tokens()->find($longId));
        $this->assertNotSame($oldAccessId, $new->accessToken->getKey());
        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_revoke_access_tokens_keeps_long_lived(): void
    {
        $user = $this->activeUser();
        app(TokenIssuer::class)->issuePair($user);

        app(TokenIssuer::class)->revokeAccessTokens($user);

        $this->assertSame(0, $user->tokens()->where('name', 'access')->count());
        $this->assertSame(1, $user->tokens()->where('name', 'long_lived')->count());
    }

    public function test_revoke_all_leaves_nothing(): void
    {
        $user = $this->activeUser();
        app(TokenIssuer::class)->issuePair($user);

        app(TokenIssuer::class)->revokeAll($user);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_access_ttl_hours_reads_config(): void
    {
        config()->set('sekarya.tokens.access_ttl_hours', 12);

        $this->assertSame(12, app(TokenIssuer::class)->accessTtlHours());
    }
}
