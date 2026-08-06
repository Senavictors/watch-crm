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
     * Status considerados "pago" para fins de faturamento, lucro e metas (RN-01
     * de docs/domain/financial-rules.md). Interino: TASK-003 substituirá este
     * critério por presença de `paid_at` — enquanto isso, é o status que aproxima
     * "o pagamento já foi confirmado e o pedido segue seu fluxo normal".
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
