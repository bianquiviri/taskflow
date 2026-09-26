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

    /**
     * The role as a value/label/tone triple ready for the UI.
     *
     * @return array{value: string, label: string, tone: string}
     */
    public function describe(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'tone' => $this->color(),
        ];
    }

    /**
     * The roles that can be granted through an invitation.
     *
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function invitable(): array
    {
        return array_map(
            fn (self $role): array => $role->describe(),
            [self::Admin, self::Member],
        );
    }

    /**
     * Every role, ready for the UI.
     *
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $role): array => $role->describe(),
            self::cases(),
        );
    }
}
