<?php

namespace App\Filament\Resources\MarketDays\Tables;

use App\Filament\Resources\MarketDays\MarketDayResource;
use App\Models\MarketDay;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MarketDaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')->label('Tag')->date('D, d.m.Y')->sortable(),
                TextColumn::make('event.name')->label('Event'),
                TextColumn::make('opens_at')->label('Team da')->state(fn (MarketDay $record) => $record->workFrom().'–'.$record->workUntil()),
                TextColumn::make('availabilities_count')->label('Können')->counts('availabilities'),
                TextColumn::make('shifts_count')->label('Eingeplant')->counts('shifts'),
                IconColumn::make('settled_at')->label('Abgerechnet')->boolean()->state(fn (MarketDay $record) => $record->settled_at !== null),
            ])
            ->filters([
                TernaryFilter::make('upcoming')->label('Zeitraum')->placeholder('alle')
                    ->trueLabel('ab heute')->falseLabel('vorbei')->default(true)
                    ->queries(
                        true: fn (Builder $q) => $q->where('date', '>=', today()),
                        false: fn (Builder $q) => $q->where('date', '<', today()),
                    ),
            ])
            ->recordActions([
                EditAction::make()->label('Planen'),
                Action::make('settle')->label('Abrechnung')->icon('heroicon-o-banknotes')
                    ->url(fn (MarketDay $record) => MarketDayResource::getUrl('settle', ['record' => $record])),
            ]);
    }
}
