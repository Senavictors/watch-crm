<?php

namespace App\Policies;

use App\Models\ProductReturn;
use App\Models\User;

/**
 * TASK-026 (CA-04) — ownership de pós-venda no nível do registro.
 *
 * A permissão (`returns.view`/`returns.update`/`returns.delete`) responde
 * "pode acessar o recurso"; esta policy responde "pode acessar ESTE
 * registro". Antes desta task o segundo nível simplesmente não existia:
 * `ReturnController::show()` devolvia qualquer devolução por ID a qualquer
 * usuário com `returns.view`, inclusive vendedor (achado 3 da auditoria).
 *
 * A regra em si vive em `ProductReturn::isVisibleTo()`, gêmea do escopo
 * `visibleTo()` usado na listagem — se as duas divergirem, a lista e o
 * detalhe passam a discordar, que é exatamente o tipo de brecha que esta
 * task fecha.
 */
class ProductReturnPolicy
{
    public function view(User $user, ProductReturn $productReturn): bool
    {
        return $productReturn->isVisibleTo($user);
    }

    public function update(User $user, ProductReturn $productReturn): bool
    {
        return $this->view($user, $productReturn);
    }

    public function delete(User $user, ProductReturn $productReturn): bool
    {
        return $this->view($user, $productReturn);
    }
}
