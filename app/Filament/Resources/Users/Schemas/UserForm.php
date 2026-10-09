<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Name')->required()->maxLength(80),
                TextInput::make('email')->label('E-Mail')->email()->required()->unique(ignoreRecord: true),
                Select::make('role')->label('Rolle')->required()->default(UserRole::Staff->value)
                    ->options(collect(UserRole::cases())->mapWithKeys(fn (UserRole $r) => [$r->value => $r->label()]))
                    ->helperText('Team: Queue und Theke. Admin: zusätzlich diese Verwaltung.'),
                TextInput::make('password')->label('Passwort')->password()->revealable()
                    ->minLength(10)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText('Leer lassen, um es nicht zu ändern.'),
            ]);
    }
}
