<?php

namespace Database\Factories;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'public_token' => Str::random(40),
            'short_code' => strtoupper(Str::random(4)),
            'status' => OrderStatus::New,
            'payment_method' => PaymentMethod::Online,
            'payment_status' => PaymentStatus::Pending,
            'source' => OrderSource::Qr,
            'total_cents' => 1000,
            'oven_units' => '1.00',
        ];
    }

    public function cash(): static
    {
        return $this->state(['payment_method' => PaymentMethod::Cash]);
    }

    public function paid(): static
    {
        return $this->state(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()]);
    }
}
