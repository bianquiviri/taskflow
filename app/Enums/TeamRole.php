<?php

declare(strict_types=1);

namespace App\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Owner => 'indigo',
            self::Admin => 'sky',
            self::Member => 'gray',
        };
    }

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], strict: true);
    }
}
