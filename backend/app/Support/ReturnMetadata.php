<?php

namespace App\Support;

class ReturnMetadata
{
    public const TYPES = ['garantia', 'troca', 'devolucao'];

    public const TYPE_LABELS = [
        'garantia'  => 'Garantia',
        'troca'     => 'Troca',
        'devolucao' => 'Devolução',
    ];

    public const STATUSES = [
        'Aguardando Recebimento',
        'Recebido',
        'Em Análise',
        'Enviado p/ Reparo',
        'Reparado',
        'Em Troca',
        'Troca Aprovada',
        'Reembolso Pendente',
        'Reembolso Efetuado',
        'Pronto p/ Reenvio',
        'Enviado',
        'Entregue',
        'Concluído',
        'Cancelado',
    ];
}
