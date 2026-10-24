<?php

namespace App\Filament\Resources\MarketDays\RelationManagers;

use App\Models\MarketDay;
use App\Models\Shift;
use App\Models\User;
use App\Support\TimeOfDay;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** The plan: who works when. After the day, set the real times here, the payout is shared by them. */
class ShiftsRelationManager extends RelationManager
{
    protected static string $relationship = 'shifts';

    protected static ?string $title = 'Einsätze';

    protected static ?string $modelLabel = 'Einsatz';

    protected static ?string $pluralModelLabel = 'Einsätze';

    public function form(Schema $schema): Schema
    {
        /** @var MarketDay $day */
        $day = $this->getOwnerRecord();

        return $schema->components([
            Select::make('user_id')->label('Wer')->required()->searchable()
                ->options(User::query()->orderBy('name')->pluck('name', 'id')),
            TextInput::make('task')->label('Aufgabe')->maxLength(120)->datalist(['Ofen', 'Kasse', 'Ausgabe', 'Aufbau', 'Abbau', 'Pause ablösen']),
            TimePicker::make('starts_at')->label('Von')->seconds(false)->required()->default($day->workFrom()),
            TimePicker::make('ends_at')->label('Bis')->seconds(false)->required()->after('starts_at')->default($day->workUntil()),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Nach dem Markttag die echten Zeiten eintragen: danach wird der Gewinn aufgeteilt.')
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('user.name')->label('Wer'),
                TextColumn::make('starts_at')->label('Zeit')
                    ->formatStateUsing(fn (Shift $record) => TimeOfDay::format($record->starts_at).'–'.TimeOfDay::format($record->ends_at)),
                TextColumn::make('hours')->label('Stunden')->state(fn (Shift $record) => number_format($record->hours(), 2, ',', '').' h'),
                TextColumn::make('task')->label('Aufgabe')->placeholder('-'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
