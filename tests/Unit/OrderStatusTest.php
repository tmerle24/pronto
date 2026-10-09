<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_happy_path_steps(): void
    {
        $this->assertSame(OrderStatus::InProgress, OrderStatus::New->next());
        $this->assertSame(OrderStatus::Ready, OrderStatus::InProgress->next());
        $this->assertSame(OrderStatus::PickedUp, OrderStatus::Ready->next());
        $this->assertNull(OrderStatus::PickedUp->next());
        $this->assertNull(OrderStatus::Cancelled->next());
    }

    public function test_terminal_states_allow_nothing(): void
    {
        foreach (OrderStatus::cases() as $target) {
            $this->assertFalse(OrderStatus::PickedUp->canTransitionTo($target));
            $this->assertFalse(OrderStatus::Cancelled->canTransitionTo($target));
        }
    }

    public function test_no_skipping_steps(): void
    {
        $this->assertFalse(OrderStatus::New->canTransitionTo(OrderStatus::Ready));
        $this->assertFalse(OrderStatus::New->canTransitionTo(OrderStatus::PickedUp));
        $this->assertTrue(OrderStatus::Ready->canTransitionTo(OrderStatus::Cancelled));
    }
}
