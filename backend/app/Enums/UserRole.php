<?php

namespace App\Enums;

use App\Support\CrmPermissions;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'gerente';
    case Seller = 'vendedor';

    public static function permissionMap(): array
    {
        return [
            self::Admin->value => CrmPermissions::admin(),
            self::Manager->value => CrmPermissions::manager(),
            self::Seller->value => CrmPermissions::seller(),
        ];
    }
}
