<?php

namespace App\Support;

class ExpenseMetadata
{
    /**
     * Categorias iniciais (RN-02, docs/domain/financial-rules.md). Lista
     * fechada, mesmo padrão de `OrderMetadata::CHANNELS`/`STATUSES` — não é
     * um cadastro livre como `Category` (catálogo, TASK-002); a task não
     * pede CRUD de categoria de despesa.
     */
    public const CATEGORIES = [
        'Marketing e Anúncios',
        'Fornecedores e Serviços',
        'Salários',
        'Fretes',
        'Embalagens',
        'Manutenção',
        'Ferramentas e Sistemas',
        'Despesas Administrativas',
        'Outros',
    ];
}
