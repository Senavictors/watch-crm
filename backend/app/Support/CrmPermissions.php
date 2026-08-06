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
        'commissions.view',
        'commissions.pay',
        'expenses.view',
        'expenses.create',
        'expenses.update',
        'expenses.delete',
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
     *
     * TASK-005: gerente também não gerencia comissão. Não vende (fora de
     * `UserRole::sellableRoles()`), então `commissions.view` só serviria pra
     * ver o relatório agregado de todos os vendedores — dado tão sensível
     * quanto lucro/margem de pedidos (RN-02), mesmo raciocínio de
     * `dashboard.financial.view`. `commissions.pay` é ação financeira
     * (movimenta o que a empresa deve a cada vendedor), reservada a
     * owner/admin como qualquer outra escrita financeira já restrita aqui.
     *
     * TASK-006: despesas gerais estão explicitamente na mesma RN-02 do
     * ADR-003 ("lucro, despesas e estoque financeiro são restritos") — sem
     * ambiguidade aqui, diferente de comissão.
     */
    public static function manager(): array
    {
        return array_values(array_diff(self::ALL, [
            'dashboard.financial.view',
            'commissions.view',
            'commissions.pay',
            'expenses.view',
            'expenses.create',
            'expenses.update',
            'expenses.delete',
        ]));
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
            // TASK-005 (RN-02): vendedor vê a própria projeção de comissão —
            // o controller/CommissionCalculator força o escopo pro próprio
            // vendedor quando ele não tem `canAccessAllRecords()`, então
            // conceder a permissão aqui não abre visão sobre outros.
            'commissions.view',
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
