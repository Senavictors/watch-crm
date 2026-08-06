<?php

namespace App\Support;

use App\Models\Product;

/**
 * Avaliação financeira do estoque (docs/domain/financial-rules.md
 * "Estoque"): custo real das unidades disponíveis e potencial de venda
 * pelos preços cadastrados, independente de período (CA-03).
 *
 * RN-01: custo usa a aquisição real da unidade/entrada — `products.cost` já
 * é o custo real pago naquela entrada (não uma média de catálogo).
 * RN-02: potencial usa o preço cadastrado — `products.price` (o preço
 * padrão do catálogo; `price_pix`/`price_card`, TASK-004, são preços por
 * forma de pagamento, não "o preço cadastrado" no sentido desta regra).
 * RN-03: só estoque físico disponível entra — `products.stock = 'IN_STOCK'`.
 * Produtos com `stock = 'SUPPLIER'` (ainda no fornecedor) ficam de fora,
 * exatamente como financial-rules.md descreve.
 *
 * CA-02 (venda/devolução/ajuste atualizam o indicador): esta classe não
 * mantém nenhum snapshot/cache — cada chamada agrega `products.qty` no
 * momento exato da consulta. Hoje só `ProductController::addQty`/edição
 * manual do catálogo alteram `qty` (confirmado por busca no código: não há
 * dedução de estoque na criação de pedido nem reposição por devolução
 * ainda implementada neste sistema); quando/se essas movimentações
 * passarem a tocar `qty`, o indicador já refletirá automaticamente, sem
 * mudança nesta classe.
 *
 * Não recebe `User` nem período: não há ownership de estoque por vendedor
 * (é um ativo da empresa, não de quem vendeu), e o indicador é "atual", não
 * histórico (RN do comportamento esperado). Quem pode consultar é decidido
 * por quem chama — mesma restrição de `dashboard.financial.view` que já
 * gateia custo/margem em Produtos e Pedidos (TASK-013); nenhuma rota nova
 * foi criada nesta task (consumidor é a API agregada da TASK-009 e os
 * dashboards da TASK-011/012, conforme "Fora de escopo"/"Frontend:
 * consumidor futuro" desta task).
 */
class InventoryValuationCalculator
{
    public static function calculate(): InventoryValuation
    {
        $row = Product::query()
            ->where('stock', 'IN_STOCK')
            ->selectRaw('
                COALESCE(SUM(cost * qty), 0) as total_cost,
                COALESCE(SUM(price * qty), 0) as total_potential_revenue,
                COALESCE(SUM(qty), 0) as total_units
            ')
            ->first();

        return new InventoryValuation(
            (float) $row->total_cost,
            (float) $row->total_potential_revenue,
            (int) $row->total_units,
        );
    }
}
