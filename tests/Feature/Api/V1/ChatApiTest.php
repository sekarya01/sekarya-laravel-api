<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ActorType;
use App\Enums\ChatRoomStatus;
use App\Enums\TaskStatus;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Task;
use App\Models\User;
use App\Support\Push\PushNotifier;
use App\Support\TaskStatusRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakePushNotifier;
use Tests\TestCase;

/**
 * Chat per task, lewat HTTP — dari DEAL sampai room dinonaktifkan.
 */
final class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $fundUsers = true;

    private User $poster;

    private FakePushNotifier $push;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        Storage::fake('public');

        $this->poster = $this->activeUser();
        $this->push = new FakePushNotifier;
        $this->app->instance(PushNotifier::class, $this->push);
    }

    /**
     * Task lewat jalur nyata: pasang, ditawar, semua diterima → DEAL.
     *
     * @param  list<User>  $workers
     * @param  list<string>  $photos  foto task SEBELUM deal (path storage)
     */
    private function dealtTask(array $workers, array $photos = []): Task
    {
        $taskId = $this->asUser($this->poster)->postJson(route('v1.tasks.store'), [
            'category_id' => $this->anyCategory()->getKey(),
            'title' => 'Bersihkan taman belakang',
            'description' => 'Rumput, daun kering, dan selokan kecil.',
            'budget_min' => 150_000,
            'city' => 'Jakarta',
            'needed_at' => now()->addDays(2)->toIso8601String(),
            'workers_needed' => count($workers),
            'publish_now' => true,
        ])->assertCreated()->json('data.id');

        if ($photos !== []) {
            Task::query()->where('ulid', $taskId)->firstOrFail()->forceFill(['photos' => $photos])->save();
        }

        foreach ($workers as $worker) {
            $bid = $this->asUser($worker)
                ->postJson(route('v1.tasks.bids.store', $taskId), ['amount' => 150_000])
                ->assertCreated()->json('data.id');
            $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bid))->assertOk();
        }

        return Task::query()->where('ulid', $taskId)->firstOrFail();
    }

    private function roomOf(Task $task): ChatRoom
    {
        return ChatRoom::query()->withTrashed()->where('task_id', $task->getKey())->firstOrFail();
    }

    private function sendText(User $as, ChatRoom $room, string $text, ?string $clientId = null): TestResponse
    {
        return $this->asUser($as)->postJson(route('v1.chat.rooms.messages.store', $room->ulid), array_filter([
            'type' => 'text',
            'caption' => $text,
            'client_message_id' => $clientId,
        ]));
    }

    // ── lahir saat DEAL ─────────────────────────────────────────────────────

    public function test_a_deal_opens_one_individual_room_with_poster_and_worker(): void
    {
        $worker = $this->activeUser();
        $task = $this->dealtTask([$worker]);
        $room = $this->roomOf($task);

        $this->asUser($worker)->getJson(route('v1.chat.rooms.show', $room->ulid))
            ->assertOk()
            ->assertJsonPath('data.room_name', 'Bersihkan taman belakang')
            ->assertJsonPath('data.task_id', $task->ulid)
            ->assertJsonPath('data.room_type', 'individual')
            ->assertJsonPath('data.room_status', 'active')
            ->assertJsonPath('data.participants_count', 2)
            ->assertJsonPath('data.permissions.can_send', true)
            ->assertJsonPath('data.last_message.content.type', 'system')
            ->assertJsonStructure(['data' => [
                'id', 'task_id', 'room_name', 'room_avatar', 'room_type', 'room_status',
                'participants' => [['id', 'type', 'name', 'avatar', 'role', 'joined_at', 'left_at',
                    'last_delivered_message_id', 'last_read_message_id', 'last_read_at']],
                'participants_count', 'last_message', 'unread_count', 'permissions', 'is_muted',
                'expired_at', 'deactivated_at', 'created_at', 'updated_at', 'deleted_at',
            ]]);

        $types = collect($this->asUser($worker)->getJson(route('v1.chat.rooms.show', $room->ulid))->json('data.participants'))
            ->pluck('type', 'id')->all();
        $this->assertSame(['user', 'worker'], [$types[$this->poster->ulid], $types[$worker->ulid]]);
    }

    public function test_room_avatar_is_null_when_task_had_no_photo_at_deal_and_stays_null(): void
    {
        $worker = $this->activeUser();
        $task = $this->dealtTask([$worker]);
        $room = $this->roomOf($task);

        $this->assertNull(ChatRoom::query()->whereKey($room->getKey())->value('avatar'));

        // Foto ditambah SESUDAH room lahir — avatar room tidak ikut berubah.
        $task->forceFill(['photos' => ['tasks/later.jpg']])->save();

        $this->asUser($worker)->getJson(route('v1.chat.rooms.show', $room->ulid))
            ->assertOk()
            ->assertJsonPath('data.room_avatar', null)
            ->assertJsonStructure(['data' => ['room_avatar']]);
    }

    public function test_room_avatar_is_frozen_to_first_task_photo_at_deal(): void
    {
        $worker = $this->activeUser();
        $task = $this->dealtTask([$worker], ['tasks/first.jpg', 'tasks/second.jpg']);
        $room = $this->roomOf($task);

        $this->assertSame('tasks/first.jpg', ChatRoom::query()->whereKey($room->getKey())->value('avatar'));

        $task->forceFill(['photos' => ['tasks/replaced.jpg']])->save();

        $avatar = $this->asUser($worker)->getJson(route('v1.chat.rooms.show', $room->ulid))
            ->assertOk()
            ->json('data.room_avatar');
        $this->assertIsString($avatar);
        $this->assertStringEndsWith('tasks/first.jpg', $avatar);
    }

    public function test_many_workers_share_one_group_room(): void
    {
        $task = $this->dealtTask([$this->activeUser(), $this->activeUser(), $this->activeUser()]);

        $this->assertSame(1, ChatRoom::query()->where('task_id', $task->getKey())->count());
        $this->asUser($this->poster)->getJson(route('v1.tasks.chat-room.show', $task->ulid))
            ->assertOk()
            ->assertJsonPath('data.room_type', 'group')
            ->assertJsonPath('data.participants_count', 4);
    }

    public function test_strangers_see_nothing(): void
    {
        $task = $this->dealtTask([$this->activeUser()]);
        $room = $this->roomOf($task);
        $stranger = $this->activeUser();

        $this->asUser($stranger)->getJson(route('v1.chat.rooms.show', $room->ulid))
            ->assertNotFound()->assertJsonPath('code', 'chat_room_not_found');
        $this->asUser($stranger)->getJson(route('v1.chat.rooms.messages.index', $room->ulid))->assertNotFound();
        $this->sendText($stranger, $room, 'halo')->assertNotFound();
        $this->asUser($stranger)->getJson(route('v1.chat.rooms.index'))->assertOk()->assertJsonCount(0, 'data');
    }

    // ── kirim & baca ────────────────────────────────────────────────────────

    /** Caption panjang membuat `data` melewati 4 KB FCM → pesan utuh dibuang, sisanya tetap. */
    public function test_a_message_too_big_for_the_push_is_left_out_of_the_data(): void
    {
        $worker = $this->activeUser();
        $room = $this->roomOf($this->dealtTask([$worker]));
        $this->push->sent = [];

        $this->sendText($worker, $room, str_repeat('panjang ', 500))->assertCreated();

        $push = $this->push->firstTo($this->poster);
        $this->assertNotNull($push);
        $this->assertArrayNotHasKey('message', $push->data);
        $this->assertSame('1', $push->data['notify']);
        $this->assertLessThanOrEqual(4096, strlen((string) json_encode($push->data)));
    }

    public function test_sending_text_notifies_the_other_side_without_touching_the_bell(): void
    {
        $worker = $this->activeUser();
        $room = $this->roomOf($this->dealtTask([$worker]));
        $this->push->sent = [];

        $this->sendText($worker, $room, 'Besok jam 8 bisa?')
            ->assertCreated()
            ->assertJsonPath('data.content.type', 'text')
            ->assertJsonPath('data.content.reference', null)
            ->assertJsonPath('data.content.size', 0)
            ->assertJsonPath('data.caption', 'Besok jam 8 bisa?')
            ->assertJsonPath('data.sender_id', $worker->ulid)
            ->assertJsonPath('data.status', 'sent');

        $push = $this->push->firstTo($this->poster);
        $this->assertNotNull($push);
        $this->assertSame('chat_message', $push->data['type']);
        $this->assertSame($room->ulid, $push->data['room_id']);
        $this->assertFalse($push->silent);
        $this->assertStringContainsString('Besok jam 8 bisa?', $push->body);
        $this->assertTrue($push->drawnByApp, 'aplikasi menggambar notifikasi gaya pesan');
        $this->assertSame('1', $push->data['notify']);
        $this->assertSame('Besok jam 8 bisa?', $push->data['preview']);
        $this->assertNotEmpty($push->data['sender_name']);
        $this->assertSame('worker', $push->data['sender_type']);
        $pushed = json_decode($push->data['message'], true);
        $this->assertSame('Besok jam 8 bisa?', $pushed['caption'], 'pesan utuh ikut di data push');
        $this->assertSame($room->ulid, $pushed['room_id']);
        $this->assertSame(0, $this->push->countTo($worker), 'pengirim tidak mengabari dirinya sendiri');
    }

    public function test_resending_with_the_same_client_id_does_not_duplicate(): void
    {
        $worker = $this->activeUser();
        $room = $this->roomOf($this->dealtTask([$worker]));
        $clientId = (string) Str::uuid();

        $first = $this->sendText($worker, $room, 'halo', $clientId)->assertCreated()->json('data.id');
        $this->sendText($worker, $room, 'halo', $clientId)->assertOk()->assertJsonPath('data.id', $first);

        $this->assertSame(1, ChatMessage::query()->where('client_message_id', $clientId)->count());
    }

    public function test_text_needs_a_caption_and_audio_drops_it(): void
    {
        $worker = $this->activeUser();
        $room = $this->roomOf($this->dealtTask([$worker]));

        $this->asUser($worker)->postJson(route('v1.chat.rooms.messages.store', $room->ulid), ['type' => 'text'])
            ->assertUnprocessable()->assertJsonValidationErrors('caption');

        $attachment = $this->asUser($worker)->post(route('v1.chat.rooms.attachments.store', $room->ulid), [
            'file' => UploadedFile::fake()->create('voice.m4a', 120, 'audio/mp4'),
            'duration' => 14,
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.type', 'audio')
            ->assertJsonPath('data.duration', 14)
            ->json('data.id');

        $this->asUser($worker)->postJson(route('v1.chat.rooms.messages.store', $room->ulid), [
            'type' => 'audio', 'attachment_id' => $attachment, 'caption' => 'diabaikan',
        ])->assertCreated()
            ->assertJsonPath('data.caption', null)
            ->assertJsonPath('data.content.duration', 14)
            ->assertJsonPath('data.content.extension', 'm4a');
    }

    /**
     * Regresi: VN `.m4a` Android dideteksi `finfo` sebagai `video/mp4` (wadah
     * MPEG-4). Harus tetap tercatat `audio` supaya pesan `audio` diterima.
     */
    public function test_an_m4a_voice_note_detected_as_video_mp4_is_still_audio(): void
    {
        $worker = $this->activeUser();
        $room = $this->roomOf($this->dealtTask([$worker]));

        $this->asUser($worker)->post(route('v1.chat.rooms.attachments.store', $room->ulid), [
            'file' => UploadedFile::fake()->create('vn_1.m4a', 60, 'video/mp4'),
            'duration' => 5,
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.type', 'audio');
    }

    public function test_image_upload_then_send_and_reply_to_it(): void
    {
        $worker = $this->activeUser();
        $room = $this->roomOf($this->dealtTask([$worker]));

        $upload = $this->asUser($worker)->post(route('v1.chat.rooms.attachments.store', $room->ulid), [
            'file' => UploadedFile::fake()->image('taman.jpg', 1600, 1200),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.width', 1600)
            ->assertJsonPath('data.height', 1200)
            ->json('data');
        Storage::disk('public')->assertExists(ChatAttachment::query()->findOrFail($upload['id'])->path);

        $image = $this->asUser($worker)->postJson(route('v1.chat.rooms.messages.store', $room->ulid), [
            'type' => 'image', 'attachment_id' => $upload['id'], 'caption' => 'Sudah mulai',
        ])->assertCreated()
            ->assertJsonPath('data.content.id', $upload['id'])
            ->assertJsonPath('data.content.file_name', 'taman.jpg')
            ->json('data.id');

        // Lampiran yang sama tidak bisa dipakai dua kali.
        $this->asUser($worker)->postJson(route('v1.chat.rooms.messages.store', $room->ulid), [
            'type' => 'image', 'attachment_id' => $upload['id'],
        ])->assertUnprocessable()->assertJsonPath('code', 'chat_attachment_invalid');

        $this->asUser($this->poster)->postJson(route('v1.chat.rooms.messages.store', $room->ulid), [
            'type' => 'reply', 'reply_type' => 'text', 'caption' => 'Bagian ini juga ya',
            'replied_message_id' => $image,
        ])->assertCreated()
            ->assertJsonPath('data.content.type', 'reply')
            ->assertJsonPath('data.content.reply_type', 'text')
            ->assertJsonPath('data.content.replied.id', $image)
            ->assertJsonPath('data.content.replied.sender_id', $worker->ulid)
            ->assertJsonPath('data.content.replied.content.type', 'image')
            ->assertJsonPath('data.content.replied.caption', 'Sudah mulai')
            ->assertJsonPath('data.content.replied.is_deleted', false);
    }

    /**
     * Video membawa thumbnail bingkai awal dari perangkat (server tanpa
     * ffmpeg): tersimpan di folder room, tampil di `thumbnail` lampiran,
     * pesan, dan kutipan balasan, dan ikut terhapus bersama pesannya.
     */
    public function test_video_thumbnail_is_stored_returned_and_deleted_with_the_message(): void
    {
        $worker = $this->activeUser();
        $room = $this->roomOf($this->dealtTask([$worker]));

        $upload = $this->asUser($worker)->post(route('v1.chat.rooms.attachments.store', $room->ulid), [
            'file' => UploadedFile::fake()->create('demo.mp4', 300, 'video/mp4'),
            'thumbnail' => UploadedFile::fake()->image('demo-thumb.jpg', 480, 270),
            'duration' => 12, 'width' => 1280, 'height' => 720,
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.type', 'video')
            ->json('data');
        $attachment = ChatAttachment::query()->findOrFail($upload['id']);
        $this->assertNotNull($attachment->thumbnail_path);
        $this->assertStringStartsWith('uploads/chat/'.$room->ulid.'/', $attachment->thumbnail_path);
        Storage::disk('public')->assertExists($attachment->thumbnail_path);
        $this->assertStringEndsWith($attachment->thumbnail_path, (string) $upload['thumbnail']);

        $video = $this->asUser($worker)->postJson(route('v1.chat.rooms.messages.store', $room->ulid), [
            'type' => 'video', 'attachment_id' => $upload['id'],
        ])->assertCreated()
            ->assertJsonPath('data.content.thumbnail', $upload['thumbnail'])
            ->json('data.id');

        $this->asUser($this->poster)->postJson(route('v1.chat.rooms.messages.store', $room->ulid), [
            'type' => 'reply', 'reply_type' => 'text', 'caption' => 'Mantap', 'replied_message_id' => $video,
        ])->assertCreated()->assertJsonPath('data.content.replied.content.thumbnail', $upload['thumbnail']);

        $this->asUser($worker)->deleteJson(route('v1.chat.messages.destroy', $video))->assertOk();
        Storage::disk('public')->assertMissing($attachment->path);
        Storage::disk('public')->assertMissing($attachment->thumbnail_path);
    }

    public function test_thumbnail_is_ignored_for_non_video_and_old_videos_have_none(): void
    {
        $worker = $this->activeUser();
        $room = $this->roomOf($this->dealtTask([$worker]));

        $doc = $this->asUser($worker)->post(route('v1.chat.rooms.attachments.store', $room->ulid), [
            'file' => UploadedFile::fake()->create('rincian.pdf', 50, 'application/pdf'),
            'thumbnail' => UploadedFile::fake()->image('x.jpg', 10, 10),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.thumbnail', null)->json('data.id');
        $this->assertNull(ChatAttachment::query()->findOrFail($doc)->thumbnail_path);

        $this->asUser($worker)->post(route('v1.chat.rooms.attachments.store', $room->ulid), [
            'file' => UploadedFile::fake()->create('lama.mp4', 100, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.thumbnail', null);
    }

    public function test_an_attachment_of_another_room_or_kind_is_refused(): void
    {
        $worker = $this->activeUser();
        $room = $this->roomOf($this->dealtTask([$worker]));
        $doc = $this->asUser($worker)->post(route('v1.chat.rooms.attachments.store', $room->ulid), [
            'file' => UploadedFile::fake()->create('rincian.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.type', 'file')->json('data.id');

        $this->asUser($worker)->postJson(route('v1.chat.rooms.messages.store', $room->ulid), [
            'type' => 'image', 'attachment_id' => $doc,
        ])->assertUnprocessable()->assertJsonPath('context.reason', 'kind_mismatch');

        // Milik orang lain.
        $this->asUser($this->poster)->postJson(route('v1.chat.rooms.messages.store', $room->ulid), [
            'type' => 'file', 'attachment_id' => $doc,
        ])->assertUnprocessable()->assertJsonPath('context.reason', 'not_found');
    }

    public function test_receipts_move_status_to_delivered_then_read_and_unread_counts_follow(): void
    {
        $worker = $this->activeUser();
        $room = $this->roomOf($this->dealtTask([$worker]));
        $id = $this->sendText($worker, $room, 'halo')->json('data.id');
        $this->sendText($worker, $room, 'masih di sana?');

        $this->asUser($this->poster)->getJson(route('v1.chat.unread-count'))->assertOk()->assertJsonPath('data.count', 2);
        $this->asUser($this->poster)->getJson(route('v1.chat.rooms.index'))->assertJsonPath('data.0.unread_count', 2);

        $this->asUser($this->poster)->postJson(route('v1.chat.rooms.receipts', $room->ulid), ['delivered_message_id' => $id])
            ->assertOk()->assertJsonPath('data.last_delivered_message_id', $id);
        $this->assertSame('delivered', $this->messageStatus($worker, $room, $id));

        $this->asUser($this->poster)->postJson(route('v1.chat.rooms.receipts', $room->ulid), ['read_message_id' => $id])->assertOk();
        $this->assertSame('read', $this->messageStatus($worker, $room, $id));
        $this->asUser($this->poster)->getJson(route('v1.chat.unread-count'))->assertJsonPath('data.count', 1);

        // Penanda tidak mundur.
        $older = ChatMessage::query()->where('room_id', $room->getKey())->oldest('id')->firstOrFail()->id;
        $this->asUser($this->poster)->postJson(route('v1.chat.rooms.receipts', $room->ulid), ['read_message_id' => $older])
            ->assertJsonPath('data.last_read_message_id', $id);

        $this->asUser($this->poster)->postJson(route('v1.chat.rooms.receipts', $room->ulid), [])
            ->assertUnprocessable();
    }

    private function messageStatus(User $as, ChatRoom $room, string $id): string
    {
        return collect($this->asUser($as)->getJson(route('v1.chat.rooms.messages.index', $room->ulid))->json('data'))
            ->firstWhere('id', $id)['status'];
    }

    public function test_messages_are_newest_first_and_after_id_syncs(): void
    {
        $worker = $this->activeUser();
        $room = $this->roomOf($this->dealtTask([$worker]));
        $a = $this->sendText($worker, $room, 'satu')->json('data.id');
        $b = $this->sendText($this->poster, $room, 'dua')->json('data.id');

        $ids = array_column($this->asUser($worker)->getJson(route('v1.chat.rooms.messages.index', $room->ulid))->json('data'), 'id');
        $this->assertSame([$b, $a], array_slice($ids, 0, 2));

        $this->asUser($worker)->getJson(route('v1.chat.rooms.messages.index', [$room->ulid, 'after_id' => $a]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $b);
    }

    public function test_deleting_keeps_a_skeleton_and_removes_the_file(): void
    {
        $worker = $this->activeUser();
        $room = $this->roomOf($this->dealtTask([$worker]));
        $upload = $this->asUser($worker)->post(route('v1.chat.rooms.attachments.store', $room->ulid), [
            'file' => UploadedFile::fake()->image('a.jpg'),
        ], ['Accept' => 'application/json'])->json('data.id');
        $path = ChatAttachment::query()->findOrFail($upload)->path;
        $id = $this->asUser($worker)->postJson(route('v1.chat.rooms.messages.store', $room->ulid), [
            'type' => 'image', 'attachment_id' => $upload, 'caption' => 'rahasia',
        ])->json('data.id');

        $this->asUser($this->poster)->deleteJson(route('v1.chat.messages.destroy', $id))
            ->assertForbidden()->assertJsonPath('code', 'chat_message_not_owned');

        $this->asUser($worker)->deleteJson(route('v1.chat.messages.destroy', $id))
            ->assertOk()
            ->assertJsonPath('data.caption', null)
            ->assertJsonPath('data.content.reference', null);
        $this->assertNotNull(ChatMessage::withTrashed()->findOrFail($id)->deleted_at);
        $this->assertNull(ChatMessage::withTrashed()->findOrFail($id)->caption);
        Storage::disk('public')->assertMissing($path);

        $this->asUser($this->poster)->getJson(route('v1.chat.rooms.show', $room->ulid))
            ->assertJsonPath('data.last_message.preview', 'Pesan dihapus');
    }

    public function test_muting_turns_the_notification_silent(): void
    {
        $worker = $this->activeUser();
        $room = $this->roomOf($this->dealtTask([$worker]));

        $this->asUser($this->poster)->patchJson(route('v1.chat.rooms.update', $room->ulid), ['is_muted' => true])
            ->assertOk()->assertJsonPath('data.is_muted', true);

        $this->push->sent = [];
        $this->sendText($worker, $room, 'halo')->assertCreated();

        $this->assertTrue($this->push->firstTo($this->poster)?->silent);
    }

    // ── berakhir ────────────────────────────────────────────────────────────

    public function test_a_finished_task_makes_the_room_read_only(): void
    {
        $worker = $this->activeUser();
        $task = $this->dealtTask([$worker]);
        $room = $this->roomOf($task);
        $this->sendText($worker, $room, 'sebelum batal')->assertCreated();

        app(TaskStatusRecorder::class)->move($task->refresh(), TaskStatus::Cancelled, ActorType::System);

        $this->asUser($worker)->getJson(route('v1.chat.rooms.show', $room->ulid))
            ->assertOk()
            ->assertJsonPath('data.room_status', 'expired')
            ->assertJsonPath('data.permissions.can_send', false)
            ->assertJsonPath('data.last_message.content.type', 'system');
        $this->assertSame('task_cancelled', ChatMessage::query()->where('room_id', $room->getKey())->latest('id')->first()?->system_event?->value);

        $this->sendText($worker, $room, 'sesudah batal')
            ->assertUnprocessable()->assertJsonPath('code', 'chat_room_expired');
        $this->asUser($worker)->getJson(route('v1.chat.rooms.messages.index', $room->ulid))->assertOk();
    }

    public function test_admin_deactivation_wipes_everything_and_answers_410(): void
    {
        $worker = $this->activeUser();
        $task = $this->dealtTask([$worker]);
        $room = $this->roomOf($task);
        $upload = $this->asUser($worker)->post(route('v1.chat.rooms.attachments.store', $room->ulid), [
            'file' => UploadedFile::fake()->image('a.jpg'),
        ], ['Accept' => 'application/json'])->json('data.id');
        $path = ChatAttachment::query()->findOrFail($upload)->path;
        $this->asUser($worker)->postJson(route('v1.chat.rooms.messages.store', $room->ulid), ['type' => 'image', 'attachment_id' => $upload]);

        $admin = $this->activeAdmin();
        $this->asAdmin($admin)->postJson(route('v1.admin.tasks.chat-room.deactivate', $task->ulid), ['reason' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->asAdmin($admin)->postJson(route('v1.admin.tasks.chat-room.deactivate', $task->ulid), [
            'reason' => 'Laporan pelecehan terverifikasi',
        ])->assertOk()->assertJsonPath('data.room_status', 'deactivated');

        $this->assertSame(0, ChatMessage::withTrashed()->where('room_id', $room->getKey())->count());
        $this->assertSame(0, ChatAttachment::query()->where('room_id', $room->getKey())->count());
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'chat_room.deactivated', 'subject_id' => $room->getKey()]);

        $this->asUser($worker)->getJson(route('v1.chat.rooms.show', $room->ulid))
            ->assertStatus(410)->assertJsonPath('code', 'chat_room_deactivated');
        $this->asUser($worker)->getJson(route('v1.chat.rooms.index'))->assertJsonCount(0, 'data');
        $this->asUser($worker)->getJson(route('v1.tasks.chat-room.show', $task->ulid))->assertOk()->assertJsonPath('data', null);
        // Orang asing tetap 404 — 410 hanya untuk peserta.
        $this->asUser($this->activeUser())->getJson(route('v1.chat.rooms.show', $room->ulid))->assertNotFound();
    }

    public function test_expired_rooms_are_purged_after_the_retention_window(): void
    {
        $worker = $this->activeUser();
        $task = $this->dealtTask([$worker]);
        $room = $this->roomOf($task);
        $this->sendText($worker, $room, 'halo');
        app(TaskStatusRecorder::class)->move($task->refresh(), TaskStatus::Cancelled, ActorType::System);

        $this->artisan('sekarya:chat:purge-expired')->assertSuccessful();
        $this->assertSame(ChatRoomStatus::Expired, $room->refresh()->status, 'belum lewat masa simpan');

        $this->travel(91)->days();
        $this->artisan('sekarya:chat:purge-expired')->assertSuccessful();

        $this->assertSame(ChatRoomStatus::Deactivated, ChatRoom::withTrashed()->findOrFail($room->getKey())->status);
        $this->assertSame(0, ChatMessage::withTrashed()->where('room_id', $room->getKey())->count());
    }
}
