<?php

namespace App\Filament\Resources\MarketDays\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class MarketDayForm
{
    /** @param  ?Model  $event  owner when created from the event, so the date is unique per event */
    public static function configure(Schema $schema, ?Model $event = null): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                DatePicker::make('date')->label('Tag')->required()->columnSpanFull()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, $record) => $rule->where('event_id', $event?->getKey() ?? $record?->event_id)),
                TimePicker::make('opens_at')->label('Geöffnet von')->seconds(false)->required()->default('10:00'),
                TimePicker::make('closes_at')->label('Geöffnet bis')->seconds(false)->required()->after('opens_at')->default('18:00'),
                TimePicker::make('setup_from')->label('Aufbau ab')->seconds(false)->default('09:00')->helperText('leer = ab Öffnung'),
                TimePicker::make('teardown_until')->label('Abbau bis')->seconds(false)->default('19:00')->helperText('leer = bis Schluss'),
            ]),
        ]);
    }
}
