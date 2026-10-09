<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\CancelOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class CancelOrderAction
{
    public static function make(): Action
    {
        return Action::make('cancel')
            ->label('Stornieren')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Order $record) => in_array($record->status, [OrderStatus::New, OrderStatus::InProgress, OrderStatus::Ready], true))
            ->requiresConfirmation()
            ->modalDescription(fn (Order $record) => $record->payment_method === PaymentMethod::Online && $record->payment_status === PaymentStatus::Paid
                ? 'Der Betrag wird über Stripe zurückerstattet.'
                : 'Bei Bar oder Karte an der Theke gebt ihr das Geld am Stand zurück.')
            ->schema([
                TextInput::make('reason')->label('Grund')->required()->maxLength(160),
                Toggle::make('refund')->label('Erstatten')->default(true)
                    ->visible(fn (Order $record) => $record->payment_status === PaymentStatus::Paid),
            ])
            ->action(function (Order $record, array $data): void {
                /** @var User $user */
                $user = Auth::user();
                try {
                    app(CancelOrder::class)($record, $user, (string) $data['reason'], (bool) ($data['refund'] ?? true));
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('Storno fehlgeschlagen')->body($e->getMessage())->send();

                    return;
                }
                Notification::make()->success()->title("Nr. {$record->pickup_number} storniert")->send();
            });
    }
}
