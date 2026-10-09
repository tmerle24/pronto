<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Bestellung')->columns(3)->schema([
                    TextEntry::make('pickup_number')->label('Abholnummer')->placeholder('-'),
                    TextEntry::make('status')->label('Status')->formatStateUsing(fn (OrderStatus $state) => $state->label()),
                    TextEntry::make('event.name')->label('Event'),
                    TextEntry::make('payment_method')->label('Zahlung')->formatStateUsing(fn (PaymentMethod $state) => $state->label()),
                    TextEntry::make('payment_status')->label('bezahlt')->formatStateUsing(fn (PaymentStatus $state) => $state->label()),
                    TextEntry::make('total_cents')->label('Summe')->money('EUR', divideBy: 100),
                    TextEntry::make('receipt_number')->label('Beleg')->placeholder('-')
                        ->url(fn (Order $o) => $o->receipt_number ? route('guest.order.receipt', $o) : null, shouldOpenInNewTab: true),
                    TextEntry::make('short_code')->label('Kurzcode'),
                    TextEntry::make('cancel_reason')->label('Stornogrund')->placeholder('-'),
                    TextEntry::make('customer_notified_at')->label('Gast benachrichtigt')->dateTime('H:i:s')->placeholder('nein'),
                    TextEntry::make('stripe_payment_intent_id')->label('Stripe')->placeholder('-')->copyable(),
                    TextEntry::make('public_token')->label('Statusseite')->formatStateUsing(fn () => 'öffnen')
                        ->url(fn (Order $o) => route('guest.order', $o), shouldOpenInNewTab: true),
                ]),
                Section::make('Positionen')->schema([
                    RepeatableEntry::make('items')->hiddenLabel()->columns(3)->schema([
                        TextEntry::make('quantity')->label('Menge'),
                        TextEntry::make('name')->label('Artikel'),
                        TextEntry::make('unit_price_cents')->label('Einzelpreis')->money('EUR', divideBy: 100),
                    ]),
                ]),
                Section::make('Verlauf (unveränderbar)')->columnSpanFull()->schema([
                    RepeatableEntry::make('events')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('created_at')->label('Zeit')->dateTime('d.m. H:i:s'),
                        TextEntry::make('type')->label('Art')->formatStateUsing(fn ($state, OrderEvent $record) => $record->type->value.($record->is_undo ? ' (rückgängig)' : '')),
                        TextEntry::make('from')->label('von')->placeholder('-'),
                        TextEntry::make('to')->label('nach')->placeholder('-'),
                    ]),
                ]),
            ]);
    }
}
