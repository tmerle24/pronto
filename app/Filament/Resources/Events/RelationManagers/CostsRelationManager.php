<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\Event;
use App\Models\MarketDay;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CostsRelationManager extends RelationManager
{
    protected static string $relationship = 'costs';

    protected static ?string $title = 'Kosten';

    protected static ?string $modelLabel = 'Kosten';

    protected static ?string $pluralModelLabel = 'Kosten';

    public function form(Schema $schema): Schema
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();

        return $schema->components([
            TextInput::make('label')->label('Wofür')->required()->maxLength(120)
                ->datalist(['Standgebühr', 'Anmeldegebühr', 'Einkauf Ware', 'Gas', 'Fahrt', 'Verpackung']),
            TextInput::make('amount_cents')->label('Betrag')->required()->numeric()->minValue(0)->step(0.01)->suffix('€')
                ->formatStateUsing(fn ($state) => $state === null ? null : $state / 100)
                ->dehydrateStateUsing(fn ($state) => (int) round((float) $state * 100)),
            Select::make('market_day_id')->label('Tag')
                ->options($event->marketDays->mapWithKeys(fn (MarketDay $d) => [$d->id => $d->dateLabel('dd, D.M.Y')]))
                ->placeholder('alle Tage (gleich aufgeteilt)')
                ->helperText('Standgebühr oder Anmeldung: leer lassen, dann trägt jeder Markttag seinen Teil.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('Wofür'),
                TextColumn::make('amount_cents')->label('Betrag')->formatStateUsing(fn (int $state) => number_format($state / 100, 2, ',', '.').' €'),
                TextColumn::make('marketDay.date')->label('Tag')->date('D, d.m.')->placeholder('alle Tage'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
