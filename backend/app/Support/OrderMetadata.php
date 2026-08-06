<?php

namespace App\Support;

class OrderMetadata
{
    public const CHANNELS = [
        'Instagram',
        'Site',
        'Mercado Livre',
        'WhatsApp',
    ];

    public const STATUSES = [
        'Novo',
        'Aguardando Pagamento',
        'Pago',
        'Separação/Fornecedor',
        'Pronto para Envio',
        'Enviado',
        'Entregue',
        'Cancelado',
    ];

    public const PAYMENT_METHODS = [
        'PIX',
        'Cartão Crédito',
        'Cartão Débito',
        'Dinheiro',
        'Boleto',
    ];

    public const SHIPPING_METHODS = [
        'Sedex',
        'Jadlog',
        'Correios PAC',
        'Retirada',
    ];

    /**
     * Status em que um pedido é considerado "pago" (TASK-003). Usado por
     * `OrderPaymentTransition` para decidir quando confirmar/reverter
     * `orders.paid_at` — a partir dessa confirmação, faturamento, lucro e metas
     * (RN-01 de docs/domain/financial-rules.md) filtram por `paid_at`, não mais
     * por este status diretamente (ver RevenueCalculator, SalesProfitCalculator,
     * GoalProgressCalculator). `ActiveOrdersCalculator` e
     * `PendingAmountCalculator` continuam status-based (RN-02 do glossário e
     * "valores aguardando pagamento" não dependem de confirmação de pagamento).
     */
    public const PAID_STATUSES = [
        'Pago',
        'Separação/Fornecedor',
        'Pronto para Envio',
        'Enviado',
        'Entregue',
    ];

    /**
     * Status somados em "valores aguardando pagamento" (financial-rules.md).
     */
    public const PENDING_PAYMENT_STATUSES = [
        'Novo',
        'Aguardando Pagamento',
    ];

    /**
     * Status excluídos do indicador "pedidos ativos" (financial-rules.md /
     * glossary.md): todo pedido que não seja Entregue nem Cancelado é ativo.
     */
    public const ACTIVE_EXCLUDED_STATUSES = [
        'Entregue',
        'Cancelado',
    ];
}
