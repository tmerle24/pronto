<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\MenuEdition;
use App\Models\Event;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Event')->columns(2)->schema([
                    TextInput::make('name')->label('Name')->required()->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set, ?Event $record) => $record === null ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->label('Kurzname in der Adresse')->required()->alphaDash()->unique(ignoreRecord: true)
                        ->helperText('Gäste-Link: /e/kurzname'),
                    TextInput::make('location')->label('Ort'),
                    CheckboxList::make('editions')->label('Karte')->required()
                        ->options(collect(MenuEdition::cases())->mapWithKeys(fn (MenuEdition $e) => [$e->value => $e->label()]))
                        ->helperText('Beide angehakt: Gäste sehen einen Umschalter.'),
                    DatePicker::make('starts_on')->label('Von')->required(),
                    DatePicker::make('ends_on')->label('Bis')->required()->afterOrEqual('starts_on'),
                    Toggle::make('is_active')->label('Aktiv (es kann nur ein Event aktiv sein)'),
                ]),
                Section::make('Bestellungen')->columns(2)->schema([
                    Toggle::make('accepting_orders')->label('Bestellannahme an')->default(true),
                    Toggle::make('cash_allowed')->label('Bar bei Abholung erlaubt')->default(true),
                    TextInput::make('pause_message')->label('Hinweis bei Pause')->maxLength(160)->columnSpanFull(),
                ]),
                Section::make('Wartezeit (Startwerte)')->columns(3)->schema([
                    TextInput::make('oven_slots')->label('Ofenplätze')->numeric()->minValue(1)->maxValue(20)->required()->default(2),
                    TextInput::make('minutes_per_bake')->label('Minuten pro Durchgang')->numeric()->minValue(1)->maxValue(30)->required()->default(4),
                    TextInput::make('buffer_minutes')->label('Puffer (Min)')->numeric()->minValue(0)->maxValue(30)->required()->default(3),
                    Text::make('Ab 5 gemessenen Bestellungen lernt das System die echte Backzeit.')->columnSpanFull(),
                ]),
            ]);
    }
}
