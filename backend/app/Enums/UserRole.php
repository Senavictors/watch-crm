<?php

namespace App\Enums;

use App\Support\CrmPermissions;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'gerente';
    case Seller = 'vendedor';
    case Guarantee = 'garantia';

    public static function values(): array
    {
        return array_map(
            static fn (self $role) => $role->value,
            self::cases()
        );
    }

    public static function permissionMap(): array
    {
        return [
            self::Admin->value     => CrmPermissions::admin(),
            self::Manager->value   => CrmPermissions::manager(),
            self::Seller->value    => CrmPermissions::seller(),
            self::Guarantee->value => CrmPermissions::guarantee(),
        ];
    }
}
