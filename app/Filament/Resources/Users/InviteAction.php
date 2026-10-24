<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use App\Support\EnumOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/** Personal link to join the team; the admin sends it by WhatsApp, no mail needed. */
class InviteAction
{
    public static function make(): Action
    {
        return Action::make('invite')
            ->label('Einladen')
            ->icon('heroicon-o-paper-airplane')
            ->modalDescription('Pronto erstellt einen persönlichen Link, gültig für '.Invitation::VALID_DAYS.' Tage. Du schickst ihn per WhatsApp, die Person legt damit selbst ihr Passwort fest.')
            ->schema([
                TextInput::make('name')->label('Name')->required()->maxLength(120),
                Select::make('role')->label('Rolle')->options(EnumOptions::for(UserRole::class))->default(UserRole::Staff->value)->required(),
            ])
            ->action(function (array $data): void {
                /** @var ?User $by */
                $by = auth()->user();
                [, $token] = Invitation::issue($data['name'], UserRole::from($data['role']), $by);
                $url = route('team.invitation', $token);
                $text = "Ciao {$data['name']}! Hier ist deine Einladung ins TIAMO-Team. Trag dich ein, wann du am Markt helfen kannst: {$url}";

                Notification::make()
                    ->success()
                    ->persistent()
                    ->title("Einladung für {$data['name']}")
                    ->body('Link ('.Invitation::VALID_DAYS." Tage gültig): {$url}")
                    ->actions([
                        Action::make('whatsapp')->label('In WhatsApp öffnen')->button()
                            ->url('https://wa.me/?text='.rawurlencode($text), shouldOpenInNewTab: true),
                    ])
                    ->send();
            });
    }
}
