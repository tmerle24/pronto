<?php

namespace App\Enums;

enum UserRole: string implements Labelled
{
    case Staff = 'staff';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Team',
            self::Admin => 'Admin',
        };
    }
}
