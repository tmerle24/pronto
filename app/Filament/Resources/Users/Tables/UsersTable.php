<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->searchable(),
                TextColumn::make('email')->label('E-Mail')->searchable(),
                TextColumn::make('role')->label('Rolle')->badge()->formatStateUsing(fn (UserRole $state) => $state->label()),
            ])
            ->recordActions([EditAction::make()]);
    }
}
