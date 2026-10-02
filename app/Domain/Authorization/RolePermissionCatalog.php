<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

/**
 * Single source of truth for the application's permissions and the default
 * role -> permission mapping. Used by the hotel-creation listener to seed a
 * fresh role/permission set on every new hotel, and by the admin UI to
 * present the editable permission grid.
 *
 * Changing this list and re-running the SeedHotelRoles listener (or the
 * `roles:sync` artisan command) keeps each hotel's tables aligned with the
 * canonical catalog.
 */
final class RolePermissionCatalog
{
    /** @var list<string> */
    public const PERMISSIONS = [
        'manage-users',
        'manage-roles',
        'manage-hotel',
        'manage-rooms',
        'manage-room-types',
        'manage-reservations',
        'manage-guests',
        'manage-folios',
        'manage-payments',
        'manage-outlets',
        'manage-menu',
        'manage-stock',
        'operate-pos',
        'view-reports',
    ];

    /** @var array<string, list<string>> */
    public const ROLES = [
        'admin' => self::PERMISSIONS,
        'manager' => [
            'manage-hotel',
            'manage-rooms',
            'manage-room-types',
            'manage-reservations',
            'manage-guests',
            'manage-folios',
            'manage-payments',
            'manage-outlets',
            'manage-menu',
            'manage-stock',
            'operate-pos',
            'view-reports',
        ],
        'accountant' => [
            'manage-folios',
            'manage-payments',
            'manage-stock',
            'view-reports',
        ],
        'front_desk' => [
            'manage-reservations',
            'manage-guests',
            'manage-folios',
        ],
        'cashier' => [
            'manage-folios',
            'manage-payments',
            'operate-pos',
        ],
        'waiter' => [
            'operate-pos',
        ],
        'staff' => [],
    ];

    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return array_keys(self::ROLES);
    }

    /**
     * @return list<string>
     */
    public static function permissionsFor(string $role): array
    {
        return self::ROLES[$role] ?? [];
    }
}
