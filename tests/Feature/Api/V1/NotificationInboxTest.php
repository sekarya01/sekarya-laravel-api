<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Push\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\FakePushNotifier;
use Tests\TestCase;

/**
 * `GET|POST /api/v1/me/notifications*` — kotak masuk in-app (B3).
 *
 * Yang penting diuji di sini: barisnya ditulis oleh JALUR YANG SAMA dengan
 * push, jadi lonceng dan notifikasi ponsel tidak pernah berbeda isi — dan
 * lonceng tetap terisi walau FCM dimatikan.
 */
final class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Satu baris kotak masuk, seperti yang ditulis PushDispatcher. */
    private function notify(User $user, string $type = 'general', ?string $at = null): UserNotification
    {
        $notification = UserNotification::query()->create([
            'user_id' => $user->getKey(),
            'type' => $type,
            'title' => 'Judul '.$type,
            'body' => 'Isi '.$type,
            'data' => ['type' => $type],
        ]);

        if ($at !== null) {
            $notification->forceFill(['created_at' => $at])->save();
        }

        return $notification;
    }

    // ── aksi nyata mengisi kotak masuk ──────────────────────────────────────

    /**
     * Penawaran masuk mengisi kotak masuk pemberi kerja, dan ISINYA sama persis
     * dengan yang dikirim ke FCM. Kalau keduanya bisa berbeda, lonceng berhenti
     * menjadi riwayat push — ia menjadi riwayat kedua yang lebih murah dimakan
     * bug.
     */
    public function test_a_real_action_fills_the_inbox_with_the_same_payload_as_the_push(): void
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();
        $task = Task::factory()->open()->create([
            'poster_id' => $poster->getKey(),
            'budget_min' => 100_000,
            'budget_max' => 200_000,
            'workers_needed' => 3,
        ]);

        $fake = new FakePushNotifier;
        $this->app->instance(PushNotifier::class, $fake);

        $this->asUser($worker)->postJson(
            route('v1.tasks.bids.store', ['task' => $task->ulid]),
            ['amount' => 120_000],
        )->assertCreated();

        $push = $fake->firstTo($poster);
        $this->assertNotNull($push, 'pemberi kerja harus menerima push penawaran masuk');

        $row = UserNotification::query()
            ->where('user_id', $poster->getKey())
            ->sole();

        $this->assertSame('bid_placed', $row->type);
        $this->assertSame($push->title, $row->title);
        $this->assertSame($push->body, $row->body);
        $this->assertSame($push->data, $row->data);

        // Dan belum dibaca — itu yang membuat badge lonceng menyala.
        $this->assertNull($row->read_at);
    }

    // ── baca ────────────────────────────────────────────────────────────────

    public function test_the_inbox_is_mine_only_and_newest_first(): void
    {
        $me = $this->activeUser();
        $other = $this->activeUser();

        $this->notify($other, 'milik_orang_lain');
        $lama = $this->notify($me, 'lama', '2026-09-20 10:00:00');
        $baru = $this->notify($me, 'baru', '2026-09-25 10:00:00');

        $body = $this->asUser($me)
            ->getJson(route('v1.me.notifications.index'))
            ->assertOk()
            ->json();

        $this->assertSame(
            [$baru->id, $lama->id],
            array_column($body['data'], 'id'),
        );
        $this->assertArrayNotHasKey('total', $body['meta'], 'daftar tetap cursor-only');
    }

    public function test_the_unread_filter_and_the_badge_count_agree(): void
    {
        $me = $this->activeUser();
        // Waktu dibuat EKSPLISIT: dalam satu milidetik yang sama, URUTAN ULID
        // tidak dijamin — assertion "terbaru dulu" tidak boleh bergantung padanya.
        $this->notify($me, 'belum', '2026-09-24 10:00:00');
        $this->notify($me, 'belum_juga', '2026-09-25 10:00:00');
        $sudah = $this->notify($me, 'sudah', '2026-09-23 10:00:00');
        $sudah->forceFill(['read_at' => now()])->save();

        $unread = $this->asUser($me)
            ->getJson(route('v1.me.notifications.index', ['unread' => 1]))
            ->assertOk()
            ->json('data.*.type');

        $this->assertSame(['belum_juga', 'belum'], $unread);

        $this->asUser($me)
            ->getJson(route('v1.me.notifications.unread-count'))
            ->assertOk()
            ->assertJsonPath('data.count', 2);
    }

    public function test_the_guest_has_no_inbox(): void
    {
        $this->getJson(route('v1.me.notifications.index'))->assertUnauthorized();
        $this->getJson(route('v1.me.notifications.unread-count'))->assertUnauthorized();
        $this->postJson(route('v1.me.notifications.read-all'))->assertUnauthorized();
    }

    // ── tandai dibaca ───────────────────────────────────────────────────────

    public function test_marking_one_read_is_scoped_to_my_inbox_and_idempotent(): void
    {
        $me = $this->activeUser();
        $other = $this->activeUser();

        $mine = $this->notify($me);
        $theirs = $this->notify($other);

        // Notifikasi orang lain dijawab 404 yang sama dengan id yang tidak ada.
        $this->asUser($me)
            ->postJson(route('v1.me.notifications.read', $theirs->id))
            ->assertNotFound();

        $this->asUser($me)
            ->postJson(route('v1.me.notifications.read', $mine->id))
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id);

        $first = $mine->refresh()->read_at;
        $this->assertNotNull($first);

        // Kedua kalinya: 200, dan waktu baca PERTAMA dipertahankan.
        $this->asUser($me)
            ->postJson(route('v1.me.notifications.read', $mine->id))
            ->assertOk();

        $this->assertTrue($first->equalTo($mine->refresh()->read_at));
    }

    public function test_marking_all_read_counts_only_what_it_changed(): void
    {
        $me = $this->activeUser();
        $other = $this->activeUser();

        $this->notify($me);
        $this->notify($me);
        $sudah = $this->notify($me);
        $sudah->forceFill(['read_at' => now()])->save();
        $this->notify($other, 'jangan_ikut_terbaca');

        // Balasannya bentuk `unread-count`: sisa belum dibaca.
        $this->asUser($me)
            ->postJson(route('v1.me.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('data.count', 0);

        // Idempoten.
        $this->asUser($me)
            ->postJson(route('v1.me.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('data.count', 0);

        $this->assertSame(0, UserNotification::query()
            ->where('user_id', $me->getKey())
            ->whereNull('read_at')
            ->count());

        $this->assertSame(1, UserNotification::query()
            ->where('user_id', $other->getKey())
            ->whereNull('read_at')
            ->count(), 'kotak masuk orang lain tidak boleh ikut tersentuh');
    }
}
