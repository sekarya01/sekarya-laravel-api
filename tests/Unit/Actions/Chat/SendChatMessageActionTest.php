<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Chat;

use App\Actions\Chat\SendChatMessageAction;
use App\Data\Chat\SendChatMessageData;
use App\Enums\ChatMessageType;
use App\Enums\ChatParticipantType;
use App\Enums\ChatRoomStatus;
use App\Enums\ChatRoomType;
use App\Exceptions\Domain\ChatRepliedMessageInvalidException;
use App\Exceptions\Domain\ChatRoomExpiredException;
use App\Exceptions\Domain\ChatRoomNotFoundException;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\ChatRoom;
use App\Models\Task;
use App\Models\User;
use App\Support\Push\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePushNotifier;
use Tests\TestCase;

/**
 * Aturan kirim pesan, DTO dibangun langsung (tanpa HTTP).
 */
final class SendChatMessageActionTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private ChatRoom $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PushNotifier::class, new FakePushNotifier);

        $this->poster = $this->activeUser();
        $this->worker = $this->activeUser();
        $task = Task::factory()->create(['poster_id' => $this->poster->getKey()]);

        $this->room = ChatRoom::query()->create(['task_id' => $task->getKey(), 'type' => ChatRoomType::Individual]);
        foreach ([[$this->poster, ChatParticipantType::User], [$this->worker, ChatParticipantType::Worker]] as [$user, $type]) {
            ChatParticipant::query()->create([
                'room_id' => $this->room->getKey(),
                'user_id' => $user->getKey(),
                'type' => $type,
                'joined_at' => now(),
            ]);
        }
    }

    private function send(User $as, SendChatMessageData $data): ChatMessage
    {
        return app(SendChatMessageAction::class)->handle($this->room->refresh(), $data, $as);
    }

    public function test_it_persists_and_marks_the_sender_as_having_read_it(): void
    {
        $message = $this->send($this->worker, new SendChatMessageData(ChatMessageType::Text, 'halo'));

        $row = ChatMessage::query()->findOrFail($message->getKey());
        $this->assertSame('halo', $row->caption);
        $this->assertSame($this->worker->getKey(), $row->sender_id);
        $this->assertSame($message->getKey(), $this->room->refresh()->last_message_id);
        $this->assertSame(
            $message->getKey(),
            ChatParticipant::query()->where('user_id', $this->worker->getKey())->value('last_read_message_id'),
        );
    }

    public function test_the_same_client_id_returns_the_same_row(): void
    {
        $first = $this->send($this->worker, new SendChatMessageData(ChatMessageType::Text, 'a', clientMessageId: 'x1'));
        $again = $this->send($this->worker, new SendChatMessageData(ChatMessageType::Text, 'a', clientMessageId: 'x1'));

        $this->assertSame($first->getKey(), $again->getKey());
        $this->assertFalse($again->wasRecentlyCreated);
        $this->assertSame(1, ChatMessage::query()->where('room_id', $this->room->getKey())->count());
    }

    public function test_a_stranger_is_told_the_room_does_not_exist(): void
    {
        $this->expectException(ChatRoomNotFoundException::class);

        $this->send($this->activeUser(), new SendChatMessageData(ChatMessageType::Text, 'halo'));
    }

    public function test_an_expired_room_refuses_messages(): void
    {
        $this->room->forceFill(['status' => ChatRoomStatus::Expired, 'expired_at' => now()])->save();

        $this->expectException(ChatRoomExpiredException::class);

        $this->send($this->worker, new SendChatMessageData(ChatMessageType::Text, 'halo'));
    }

    public function test_replying_to_a_message_of_another_room_is_refused(): void
    {
        $other = ChatRoom::query()->create([
            'task_id' => Task::factory()->create()->getKey(),
            'type' => ChatRoomType::Individual,
        ]);
        $foreign = ChatMessage::query()->create([
            'room_id' => $other->getKey(),
            'type' => ChatMessageType::Text,
            'caption' => 'di room lain',
        ]);

        $this->expectException(ChatRepliedMessageInvalidException::class);

        $this->send($this->worker, new SendChatMessageData(
            ChatMessageType::Reply,
            'balas',
            replyType: ChatMessageType::Text,
            repliedMessageId: $foreign->getKey(),
        ));
    }
}
