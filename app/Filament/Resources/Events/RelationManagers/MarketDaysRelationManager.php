<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Filament\Resources\MarketDays\MarketDayResource;
use App\Filament\Resources\MarketDays\Schemas\MarketDayForm;
use App\Models\Event;
use App\Models\MarketDay;
use App\Support\TimeOfDay;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MarketDaysRelationManager extends RelationManager
{
    protected static string $relationship = 'marketDays';

    protected static ?string $title = 'Markttage';

    protected static ?string $modelLabel = 'Markttag';

    protected static ?string $pluralModelLabel = 'Markttage';

    public function form(Schema $schema): Schema
    {
        return MarketDayForm::configure($schema, $this->getOwnerRecord());
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')->label('Tag')->date('D, d.m.Y'),
                TextColumn::make('opens_at')->label('Geöffnet')
                    ->formatStateUsing(fn (MarketDay $record) => TimeOfDay::format($record->opens_at).'–'.TimeOfDay::format($record->closes_at)),
                TextColumn::make('setup_from')->label('Team da')
                    ->state(fn (MarketDay $record) => $record->workFrom().'–'.$record->workUntil()),
                TextColumn::make('availabilities_count')->label('Können')->counts('availabilities'),
                TextColumn::make('shifts_count')->label('Eingeplant')->counts('shifts'),
            ])
            ->headerActions([
                CreateAction::make(),
                // one click for the whole event: every day from start to end, 10-18, team 9-19
                Action::make('fill')
                    ->label('Alle Tage anlegen')
                    ->icon('heroicon-o-calendar-days')
                    ->action(function (): void {
                        /** @var Event $event */
                        $event = $this->getOwnerRecord();
                        $created = 0;
                        for ($date = $event->starts_on; $date->lte($event->ends_on); $date = $date->addDay()) {
                            $day = MarketDay::query()->firstOrCreate(
                                ['event_id' => $event->id, 'date' => $date->toDateString()],
                                ['opens_at' => '10:00', 'closes_at' => '18:00', 'setup_from' => '09:00', 'teardown_until' => '19:00'],
                            );
                            $created += $day->wasRecentlyCreated ? 1 : 0;
                        }
                        Notification::make()->success()->title($created ? "{$created} Markttage angelegt" : 'Alle Tage gibt es schon')->send();
                    }),
            ])
            ->recordActions([
                Action::make('plan')->label('Planen')->icon('heroicon-o-user-group')
                    ->url(fn (MarketDay $record) => MarketDayResource::getUrl('edit', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
