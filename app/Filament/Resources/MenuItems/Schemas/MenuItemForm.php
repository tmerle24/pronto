<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use App\Enums\ItemVariant;
use App\Enums\MenuCategory;
use App\Enums\MenuEdition;
use App\Support\EnumOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->columns(2)
            ->components([
                Section::make('Artikel')->columns(2)->schema([
                    TextInput::make('name')->label('Name')->required()->maxLength(80),
                    TextInput::make('name_en')->label('Name (englisch)')->maxLength(80),
                    TextInput::make('description')->label('Beschreibung')->maxLength(160),
                    TextInput::make('description_en')->label('Beschreibung (englisch)')->maxLength(160),
                    Select::make('category')->label('Kategorie')->options(EnumOptions::for(MenuCategory::class))->required(),
                    Select::make('edition')->label('Karte')->options(EnumOptions::for(MenuEdition::class))
                        ->placeholder('Immer (z. B. Getränke)'),
                    Select::make('variant')->label('Variante')->options(EnumOptions::for(ItemVariant::class))->placeholder('keine'),
                    TextInput::make('sort')->label('Sortierung')->numeric()->default(100),
                ]),
                Section::make('Preis')->columns(3)->schema([
                    TextInput::make('price_cents')->label('Preis (Cent)')->numeric()->minValue(0)->required()->helperText('950 = 9,50 €'),
                    TextInput::make('deposit_cents')->label('Pfand (Cent)')->numeric()->minValue(0)->default(0),
                    Select::make('vat_rate_bp')->label('MwSt.')->options([700 => '7 % (Speisen)', 1900 => '19 % (Getränke)'])->required()->default(700),
                ]),
                Section::make('Küche und Hinweise')->columns(2)->schema([
                    TextInput::make('oven_units')->label('Ofeneinheiten')->numeric()->step(0.25)->minValue(0)->maxValue(4)->required()->default(1)
                        ->helperText('Ganze Pizza 1, Stück 0,25, Getränk 0'),
                    TextInput::make('allergens')->label('Allergene')->maxLength(60),
                    TextInput::make('additives')->label('Zusatzstoffe')->maxLength(60),
                    Toggle::make('is_available')->label('Auf der Karte')->default(true),
                    Toggle::make('is_sold_out')->label('Ausverkauft'),
                ]),
            ]);
    }
}
