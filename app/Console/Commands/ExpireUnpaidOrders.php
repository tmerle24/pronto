<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Orders\OrderPayments;
use Illuminate\Console\Command;

class ExpireUnpaidOrders extends Command
{
    public const MINUTES = 30;

    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Cancel online orders not paid within 30 minutes';

    public function handle(OrderPayments $payments): int
    {
        $count = 0;
        Order::query()
            ->where('payment_method', PaymentMethod::Online)
            ->where('payment_status', PaymentStatus::Pending)
            ->where('status', OrderStatus::New)
            ->whereNull('queued_at')
            ->where('created_at', '<', now()->subMinutes(self::MINUTES))
            ->each(function (Order $order) use ($payments, &$count): void {
                $payments->expire($order);
                $count++;
            });

        $this->info("Expired: {$count}");

        return self::SUCCESS;
    }
}
