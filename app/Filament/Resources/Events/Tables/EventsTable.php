<?php

namespace App\Filament\Resources\Events\Tables;

use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('name')->label('Event')->searchable()->description(fn (Event $e) => $e->location),
                TextColumn::make('starts_on')->label('Von')->date('d.m.Y')->sortable(),
                TextColumn::make('ends_on')->label('Bis')->date('d.m.Y'),
                IconColumn::make('is_active')->label('Aktiv')->boolean(),
                IconColumn::make('accepting_orders')->label('Annahme')->boolean(),
                IconColumn::make('cash_allowed')->label('Bar')->boolean(),
                TextColumn::make('orders_count')->label('Bestellungen')->counts('orders'),
            ])
            ->recordActions([
                Action::make('qr')->label('QR-Code')->icon('heroicon-o-qr-code')
                    ->url(fn (Event $e) => route('admin.events.qr', $e), shouldOpenInNewTab: true),
                Action::make('display')->label('Display')->icon('heroicon-o-tv')
                    ->url(fn (Event $e) => route('display', $e->display_token), shouldOpenInNewTab: true),
                EditAction::make(),
            ]);
    }
}
