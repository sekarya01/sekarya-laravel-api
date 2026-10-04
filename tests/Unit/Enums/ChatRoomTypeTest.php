<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\ChatRoomType;
use PHPUnit\Framework\TestCase;

final class ChatRoomTypeTest extends TestCase
{
    public function test_single_worker_task_is_individual(): void
    {
        $this->assertSame(ChatRoomType::Individual, ChatRoomType::forTask(1, 1));
    }

    public function test_quota_above_one_is_group_even_with_one_worker_hired(): void
    {
        $this->assertSame(ChatRoomType::Group, ChatRoomType::forTask(3, 1));
    }

    public function test_more_than_one_accepted_worker_is_group(): void
    {
        $this->assertSame(ChatRoomType::Group, ChatRoomType::forTask(1, 2));
    }
}
