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
}
