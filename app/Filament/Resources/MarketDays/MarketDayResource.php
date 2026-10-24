<?php

namespace App\Filament\Resources\MarketDays;

use App\Filament\Resources\MarketDays\Pages\EditMarketDay;
use App\Filament\Resources\MarketDays\Pages\ListMarketDays;
use App\Filament\Resources\MarketDays\Pages\SettleMarketDay;
use App\Filament\Resources\MarketDays\RelationManagers\AvailabilitiesRelationManager;
use App\Filament\Resources\MarketDays\RelationManagers\ShiftsRelationManager;
use App\Filament\Resources\MarketDays\Schemas\MarketDayForm;
use App\Filament\Resources\MarketDays\Tables\MarketDaysTable;
use App\Models\MarketDay;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Shift planning per market day; days are created at the event. */
class MarketDayResource extends Resource
{
    protected static ?string $model = MarketDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Einsatzplanung';

    protected static ?string $modelLabel = 'Markttag';

    protected static ?string $pluralModelLabel = 'Markttage';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return MarketDayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketDaysTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AvailabilitiesRelationManager::class,
            ShiftsRelationManager::class,
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketDays::route('/'),
            'edit' => EditMarketDay::route('/{record}/edit'),
            'settle' => SettleMarketDay::route('/{record}/abrechnung'),
        ];
    }
}
