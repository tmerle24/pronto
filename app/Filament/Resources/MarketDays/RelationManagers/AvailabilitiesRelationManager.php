<?php

namespace App\Filament\Resources\MarketDays\RelationManagers;

use App\Models\Availability;
use App\Models\Shift;
use App\Support\TimeOfDay;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** What the team entered on "Turni"; one click turns it into a shift. */
class AvailabilitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'availabilities';

    protected static ?string $title = 'Wer kann?';

    protected static ?string $modelLabel = 'Verfügbarkeit';

    protected static ?string $pluralModelLabel = 'Verfügbarkeiten';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Trägt jeder selbst ein (Team, "Turni"). "Einplanen" übernimmt die Zeit als Einsatz, anpassbar.')
            ->defaultSort('available_from')
            ->columns([
                TextColumn::make('user.name')->label('Wer'),
                TextColumn::make('available_from')->label('Kann')
                    ->formatStateUsing(fn (Availability $record) => TimeOfDay::format($record->available_from).'–'.TimeOfDay::format($record->available_until)),
                TextColumn::make('note')->label('Notiz')->placeholder('-')->wrap(),
                TextColumn::make('planned')->label('Eingeplant')
                    ->state(fn (Availability $record) => self::planned($record) ? 'ja' : 'nein')
                    ->badge()->color(fn (string $state) => $state === 'ja' ? 'success' : 'gray'),
            ])
            ->recordActions([
                // once planned, times are changed under "Einsätze", so nobody is planned twice by accident
                Action::make('plan')->label('Einplanen')->icon('heroicon-o-plus')
                    ->hidden(fn (Availability $record) => self::planned($record))
                    ->fillForm(fn (Availability $record) => [
                        'starts_at' => TimeOfDay::format($record->available_from),
                        'ends_at' => TimeOfDay::format($record->available_until),
                    ])
                    ->schema([
                        TimePicker::make('starts_at')->label('Von')->seconds(false)->required(),
                        TimePicker::make('ends_at')->label('Bis')->seconds(false)->required()->after('starts_at'),
                        TextInput::make('task')->label('Aufgabe')->maxLength(120)->datalist(['Ofen', 'Kasse', 'Ausgabe', 'Aufbau', 'Abbau', 'Pause ablösen']),
                    ])
                    ->action(function (Availability $record, array $data): void {
                        Shift::query()->create([
                            'market_day_id' => $record->market_day_id,
                            'user_id' => $record->user_id,
                            'starts_at' => $data['starts_at'],
                            'ends_at' => $data['ends_at'],
                            'task' => $data['task'] ?? null,
                        ]);
                        Notification::make()->success()->title("{$record->user->name} eingeplant")->send();
                    }),
            ]);
    }

    private static function planned(Availability $record): bool
    {
        return Shift::query()->where('market_day_id', $record->market_day_id)->where('user_id', $record->user_id)->exists();
    }
}
