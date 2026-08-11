<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * TASK-024: estoque insuficiente para atender a operação. Renderizada como
 * 422 com `code = insufficient_stock` em `bootstrap/app.php`, para o frontend
 * distinguir esse conflito de um erro de validação comum.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly string $productLabel,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(
            "Estoque insuficiente para {$productLabel}: {$requested} unidade(s) solicitada(s), {$available} disponível(is)."
        );
    }
}
