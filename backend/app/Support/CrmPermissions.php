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
        'categories.view',
        'categories.create',
        'categories.update',
        'categories.delete',
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
        'dashboard.financial.view',
    ];

    /**
     * TASK-013 (ADR-003) — proprietário (Josué): mesmo acesso operacional de
     * admin, mais as exclusividades financeiras (`dashboard.financial.view`
     * já incluída em `ALL`). Papel deliberadamente separado de `admin` para
     * não depender de nome/ID fixo (RN-03) — quem tiver este papel tem as
     * exclusividades, não uma pessoa específica.
     */
    public static function owner(): array
    {
        return self::ALL;
    }

    public static function admin(): array
    {
        return self::ALL;
    }

    /**
     * Gerente perde `dashboard.financial.view` (RN-02: lucro, despesas e
     * estoque financeiro são restritos) — é a diferença real entre admin e
     * gerente que o ADR-003 pede; antes desta task os dois eram idênticos.
     */
    public static function manager(): array
    {
        return array_values(array_diff(self::ALL, ['dashboard.financial.view']));
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
