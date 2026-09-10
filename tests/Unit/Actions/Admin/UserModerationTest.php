<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Admin;

use App\Actions\Admin\User\ChangeUserStatusAction;
use App\Actions\Admin\User\ListUsersAction;
use App\Data\Admin\ChangeUserStatusData;
use App\Data\Admin\UserQueueData;
use App\Data\CursorPageData;
use App\Enums\AdminAction;
use App\Enums\UserStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Http\Requests\Api\V1\Admin\ModerateUserRequest;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Support\TokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class UserModerationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->activeAdmin();
        $this->user = $this->activeUser();
    }

    private function moderateRequest(string $reason = 'Menipu tiga pemberi kerja berturut-turut.'): ModerateUserRequest
    {
        $request = ModerateUserRequest::create('/', 'POST', ['reason' => $reason]);
        $request->server->set('REMOTE_ADDR', '10.0.0.5');

        return $request;
    }

    private function apply(ChangeUserStatusData $data): User
    {
        return app(ChangeUserStatusAction::class)->handle($this->user, $this->admin, $data);
    }

    public function test_suspending_writes_the_status_and_the_audit_row(): void
    {
        $result = $this->apply(ChangeUserStatusData::suspend($this->moderateRequest()));

        $this->assertSame(UserStatus::Suspended, $result->status);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->getKey(),
            'status' => UserStatus::Suspended->value,
        ]);

        $row = AdminAuditLog::query()
            ->forAction(AdminAction::UserSuspended, (int) $this->user->getKey())
            ->sole();

        $this->assertSame('Menipu tiga pemberi kerja berturut-turut.', $row->reason);
        $this->assertSame('user', $row->subject_type);
    }

    /**
     * INI yang benar-benar menghentikan orangnya.
     *
     * Status saja tidak: access token hidup delapan jam dan tidak menyimpan
     * status di dalamnya, jadi akun yang dinonaktifkan tanpa pencabutan token
     * tetap bisa menawar dan mengerjakan task sampai tokennya kedaluwarsa.
     * Long_lived-nya bahkan 30 hari.
     */
    public function test_suspending_revokes_every_token_the_user_holds(): void
    {
        app(TokenIssuer::class)->issuePair($this->user);
        $this->assertSame(2, $this->user->tokens()->count());

        $this->apply(ChangeUserStatusData::suspend($this->moderateRequest()));

        $this->assertSame(0, $this->user->tokens()->count());
    }

    public function test_banning_revokes_tokens_too(): void
    {
        app(TokenIssuer::class)->issuePair($this->user);

        $result = $this->apply(ChangeUserStatusData::ban($this->moderateRequest()));

        $this->assertSame(UserStatus::Banned, $result->status);
        $this->assertSame(0, $this->user->tokens()->count());
    }

    public function test_reinstating_a_verified_account_returns_it_to_active(): void
    {
        $this->apply(ChangeUserStatusData::suspend($this->moderateRequest()));

        $result = $this->apply(ChangeUserStatusData::reinstate(Request::create('/', 'POST')));

        $this->assertSame(UserStatus::Active, $result->status);
        $this->assertTrue($result->status->canReceiveTokens());
    }

    /**
     * Moderasi tidak boleh menjadi jalan melewati verifikasi email.
     *
     * Akun yang belum pernah memasukkan kode dikembalikan ke
     * `pending_verification`, bukan ke `active` — kalau tidak, suspend lalu
     * pulihkan akan mengaktifkan akun yang emailnya belum pernah terbukti.
     */
    public function test_reinstating_an_unverified_account_returns_it_to_pending_verification(): void
    {
        $unverified = User::factory()->unverified()->create();
        $unverified->forceFill(['status' => UserStatus::Suspended])->save();

        $result = app(ChangeUserStatusAction::class)->handle(
            $unverified,
            $this->admin,
            ChangeUserStatusData::reinstate(Request::create('/', 'POST')),
        );

        $this->assertSame(UserStatus::PendingVerification, $result->status);
        $this->assertFalse($result->status->canReceiveTokens());
        $this->assertDatabaseHas('users', [
            'id' => $unverified->getKey(),
            'status' => UserStatus::PendingVerification->value,
        ]);
    }

    public function test_suspending_an_already_suspended_account_is_rejected(): void
    {
        $this->apply(ChangeUserStatusData::suspend($this->moderateRequest()));

        try {
            $this->apply(ChangeUserStatusData::suspend($this->moderateRequest()));
            $this->fail('transisi ke status yang sama seharusnya ditolak');
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame(['from' => 'suspended', 'to' => 'suspended'], $e->context());
        }

        // Dan tidak menambah jejak untuk tindakan yang tidak terjadi.
        $this->assertSame(1, AdminAuditLog::query()
            ->forAction(AdminAction::UserSuspended, (int) $this->user->getKey())
            ->count());
    }

    public function test_reinstating_an_active_account_is_rejected(): void
    {
        $this->expectException(InvalidStatusTransitionException::class);

        $this->apply(ChangeUserStatusData::reinstate(Request::create('/', 'POST')));
    }

    // ── Daftar ──────────────────────────────────────────────────────────────

    public function test_users_can_be_filtered_by_status_and_by_exact_email(): void
    {
        $suspended = $this->activeUser(['email' => 'nakal@sekarya.test']);
        $suspended->forceFill(['status' => UserStatus::Suspended])->save();

        $byStatus = app(ListUsersAction::class)
            ->handle(new UserQueueData(new CursorPageData(50), UserStatus::Suspended))
            ->pluck('id')
            ->all();

        $this->assertContains($suspended->getKey(), $byStatus);
        $this->assertNotContains($this->user->getKey(), $byStatus);

        $byEmail = app(ListUsersAction::class)
            ->handle(new UserQueueData(new CursorPageData(50), null, 'nakal@sekarya.test'))
            ->pluck('id')
            ->all();

        $this->assertSame([$suspended->getKey()], $byEmail);
    }

    /**
     * Pencarian pengguna TIDAK memakai LIKE.
     *
     * `%budi%` tidak bisa memakai indeks apa pun, jadi setiap ketikan akan
     * memindai seluruh tabel pengguna. Invarian ini berlaku di seluruh
     * `app/` dan diperiksa di sini pada kueri yang paling menggodanya.
     */
    public function test_the_user_query_never_uses_like(): void
    {
        $sql = User::query()
            ->where('email', 'seseorang@sekarya.test')
            ->where('status', UserStatus::Active)
            ->toSql();

        $this->assertStringNotContainsStringIgnoringCase('like', $sql);
    }
}
