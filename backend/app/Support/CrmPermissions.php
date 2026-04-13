<?php

namespace App\Support;

class CrmPermissions
{
    public const ALL = [
        'dashboard.view',
        'shipping.view',
        'customers.view',
        'customers.create',
        'customers.update',
        'customers.delete',
        'products.view',
        'products.create',
        'products.update',
        'products.delete',
        'brands.view',
        'brands.create',
        'brands.update',
        'brands.delete',
        'qualities.view',
        'qualities.create',
        'qualities.update',
        'qualities.delete',
        'models.view',
        'models.create',
        'models.update',
        'models.delete',
        'orders.view',
        'orders.create',
        'orders.update',
        'orders.delete',
        'returns.view',
        'returns.create',
        'returns.update',
        'returns.delete',
        'goals.view',
        'goals.create',
        'goals.update',
        'goals.delete',
        'settings.view',
        'users.manage',
    ];

    public static function admin(): array
    {
        return self::ALL;
    }

    public static function manager(): array
    {
        return self::ALL;
    }

    public static function seller(): array
    {
        return [
            'dashboard.view',
            'shipping.view',
            'customers.view',
            'products.view',
            'models.view',
            'orders.view',
            'returns.view',
            'goals.view',
        ];
    }

    public static function guarantee(): array
    {
        return [
            'shipping.view',
            'customers.view',
            'products.view',
            'models.view',
            'returns.view',
            'returns.create',
            'returns.update',
        ];
    }
}
