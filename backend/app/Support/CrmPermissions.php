<?php

namespace App\Support;

class CrmPermissions
{
    public const ALL = [
        'dashboard.view',
        'shipping.view',
        // TASK-016 (CA-01): "dias são configuráveis por usuário autorizado" —
        // mesmo tier de `settings.view` (owner/admin/gerente). Não atribuída
        // explicitamente em `seller()`/`guarantee()` (listas explícitas, já
        // ficam de fora por padrão) — só quem já configura o resto do
        // sistema pode reconfigurar a agenda de postagem.
        'shipping.update',
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
        // TASK-027 (ADR-008) — escrita financeira deixa de ser efeito
        // colateral de permissão operacional. `orders.update` edita o
        // pedido; confirmar/reverter pagamento (que grava `paid_at` e
        // dispara faturamento, meta, comissão e baixa de estoque) exige
        // esta permissão dedicada.
        'orders.payment.confirm',
        'returns.view',
        'returns.create',
        'returns.update',
        'returns.delete',
        // Aprovar reembolso é dinheiro saindo: `garantia` e `gerente`
        // tocam o fluxo até "Reembolso Pendente" e param ali.
        'returns.refund.approve',
        // Gateia um CAMPO (`refund_amount`), não uma rota — mesmo padrão
        // documentado de `dashboard.financial.view` (TASK-013). Custos
        // operacionais da devolução (frete, relojoeiro) seguem em
        // `returns.update`, com quem tem o dado.
        'returns.financials.update',
        'goals.view',
        'goals.create',
        'goals.update',
        'goals.delete',
        'settings.view',
        'users.manage',
        'dashboard.financial.view',
        // TASK-021 — piloto do resumo inteligente. A geração e a gestão da
        // credencial ficam separadas para permitir ampliar a leitura no
        // futuro sem conceder acesso ao segredo do provedor.
        'ai.summary.generate',
        'ai.settings.view',
        'ai.settings.update',
        'commissions.view',
        'commissions.pay',
        'expenses.view',
        'expenses.create',
        'expenses.update',
        'expenses.delete',
        // TASK-018 — lista de espera por produto. Recurso novo, sem
        // dependência de `dashboard.financial.view`/RN-02 financeira (não
        // cria oportunidade financeira, ver RN-01 da task): entra em `ALL`
        // igual a qualquer outro CRUD operacional, sem exclusão em
        // `manager()` abaixo.
        'waitlist.view',
        'waitlist.create',
        'waitlist.update',
        'waitlist.delete',
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
    /**
     * TASK-027 (ADR-008): gerente MANTÉM `orders.payment.confirm` — toca a
     * operação do dia a dia e precisa marcar pedido como pago; o que ele
     * continua sem ver é lucro/custo/margem. Já a aprovação de reembolso
     * (`returns.refund.approve`) e o valor do reembolso
     * (`returns.financials.update`) saem: são dinheiro voltando ao cliente,
     * no mesmo tier de comissões e despesas que ele já não acessa.
     */
    public static function manager(): array
    {
        return array_values(array_diff(self::ALL, [
            'dashboard.financial.view',
            'returns.refund.approve',
            'returns.financials.update',
            'ai.summary.generate',
            'ai.settings.view',
            'ai.settings.update',
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
            // TASK-018 (RN-02): vendedor registra e atualiza as próprias
            // entradas de lista de espera — `WaitlistController` força o
            // escopo por `seller_user_id` (index) e checa ownership direto
            // (update/destroy), então conceder create/update aqui não abre
            // acesso a entradas de outro vendedor. Sem `waitlist.delete`:
            // nota-se que `returns.*` hoje só concede `returns.view` ao
            // vendedor (nem create nem update — checado em `CrmPermissions::
            // seller()` antes desta task), então este não é literalmente "o
            // mesmo padrão de returns.*". Optamos por seguir a decisão
            // explícita da task (view/create/update, sem delete) em vez de
            // replicar `returns.*`; ver relatório da TASK-018 para o agente
            // `auth-permissoes` confirmar/ajustar se achar inconsistente.
            'waitlist.view',
            'waitlist.create',
            'waitlist.update',
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
