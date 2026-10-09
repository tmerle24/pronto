<?php

namespace App\Filament\Resources\MenuItems\Tables;

use App\Enums\MenuEdition;
use App\Events\EventSettingsChanged;
use App\Models\Event;
use App\Models\MenuItem;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        $changed = function (): void {
            if ($event = Event::current()) {
                EventSettingsChanged::dispatch($event);
            }
        };

        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')->label('Artikel')->searchable()
                    ->description(fn (MenuItem $i) => $i->variant?->label()),
                TextColumn::make('edition')->label('Karte')->formatStateUsing(fn (?MenuEdition $state) => $state?->label() ?? 'immer'),
                TextColumn::make('price_cents')->label('Preis')->money('EUR', divideBy: 100)->sortable(),
                TextColumn::make('deposit_cents')->label('Pfand')->money('EUR', divideBy: 100),
                TextColumn::make('vat_rate_bp')->label('MwSt.')->formatStateUsing(fn (int $state) => ($state / 100).' %'),
                ToggleColumn::make('is_available')->label('Auf der Karte')->afterStateUpdated($changed),
                ToggleColumn::make('is_sold_out')->label('Ausverkauft')->afterStateUpdated($changed),
            ])
            ->filters([
                SelectFilter::make('edition')->label('Karte')
                    ->options(collect(MenuEdition::cases())->mapWithKeys(fn (MenuEdition $e) => [$e->value => $e->label()])),
            ])
            ->recordActions([EditAction::make()]);
    }
}
