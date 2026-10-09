<?php

namespace App\Enums;

enum UserRole: string
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
