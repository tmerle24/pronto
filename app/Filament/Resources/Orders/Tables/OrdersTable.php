<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\CancelOrderAction;
use App\Models\Event;
use App\Support\EnumOptions;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->defaultSort('id', 'desc')
            ->poll('15s')
            ->columns([
                TextColumn::make('pickup_number')->label('Nr.')->sortable()->placeholder('-'),
                TextColumn::make('created_at')->label('Zeit')->dateTime('d.m. H:i')->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                    ->color(fn (OrderStatus $state) => match ($state) {
                        OrderStatus::Ready => 'success',
                        OrderStatus::Cancelled => 'danger',
                        OrderStatus::InProgress => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('payment_method')->label('Zahlung')->formatStateUsing(fn (PaymentMethod $state) => $state->label()),
                TextColumn::make('payment_status')->label('bezahlt')->badge()
                    ->formatStateUsing(fn (PaymentStatus $state) => $state->label())
                    ->color(fn (PaymentStatus $state) => match ($state) {
                        PaymentStatus::Paid => 'success',
                        PaymentStatus::Refunded, PaymentStatus::Failed => 'danger',
                        PaymentStatus::Pending => 'warning',
                    }),
                TextColumn::make('total_cents')->label('Summe')->money('EUR', divideBy: 100),
                TextColumn::make('source')->label('Quelle')->formatStateUsing(fn (OrderSource $state) => $state === OrderSource::Qr ? 'QR' : 'Theke'),
                TextColumn::make('receipt_number')->label('Beleg')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event_id')->label('Event')->options(fn () => Event::query()->orderByDesc('starts_on')->pluck('name', 'id'))
                    ->default(fn () => Event::current()?->id),
                SelectFilter::make('status')->label('Status')->options(EnumOptions::for(OrderStatus::class)),
                SelectFilter::make('payment_method')->label('Zahlung')->options(EnumOptions::for(PaymentMethod::class)),
                SelectFilter::make('payment_status')->label('bezahlt')->options(EnumOptions::for(PaymentStatus::class)),
            ])
            ->recordActions([ViewAction::make(), CancelOrderAction::make()]);
    }
}
