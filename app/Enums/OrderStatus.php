<?php

namespace App\Enums;

/**
 * Kitchen workflow. Payment is tracked separately (PaymentStatus).
 */
enum OrderStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case PickedUp = 'picked_up';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::New => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Ready, self::Cancelled],
            self::Ready => [self::PickedUp, self::Cancelled],
            self::PickedUp, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    /** Next step for the one-tap button on the queue. */
    public function next(): ?self
    {
        return match ($this) {
            self::New => self::InProgress,
            self::InProgress => self::Ready,
            self::Ready => self::PickedUp,
            self::PickedUp, self::Cancelled => null,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::InProgress, self::Ready], true);
    }

    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::New => null,
            self::InProgress => 'started_at',
            self::Ready => 'ready_at',
            self::PickedUp => 'picked_up_at',
            self::Cancelled => 'cancelled_at',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'Neu',
            self::InProgress => 'Im Ofen',
            self::Ready => 'Pronto',
            self::PickedUp => 'Abgeholt',
            self::Cancelled => 'Storniert',
        };
    }
}
