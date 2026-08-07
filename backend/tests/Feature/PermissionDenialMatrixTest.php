<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-015 (RN-02) — matriz de NEGAÇÃO de permissão.
 *
 * Cobre rotas protegidas por `permission:` em `routes/api.php` que ainda não
 * tinham nenhum teste de um papel SEM a permissão recebendo 403 — cobertura
 * só de acesso permitido não garante que o middleware/atribuição de papel em
 * `CrmPermissions.php` realmente bloqueia quem não deveria entrar.
 *
 * Critério de escolha do papel testado por rota: o papel mais "próximo" que
 * tem tudo MENOS a permissão exigida (ex.: vendedor tem `products.view` mas
 * não `products.update` — testar vendedor prova que a fronteira view/escrita
 * é respeitada, não só ausência total de acesso). Quando nenhum papel fica
 * "só faltando essa" (ex.: `qualities.*`, `brands.create/update/delete`, onde
 * vendedor/garantia não têm nenhuma permissão do recurso), usa-se vendedor
 * por ser o papel operacional padrão já usado nos testes de negação
 * existentes (CategoryControllerTest, AuthFlowTest).
 *
 * PUT e PATCH em `routes/api.php` apontam para o mesmo controller/método e a
 * mesma string de `permission:<recurso>.update` — testar um dos dois verbos
 * por recurso já comprova a configuração; não duplicamos caso por verbo.
 *
 * Rotas de leitura (`*.view`) que TODO papel autenticado possui hoje
 * (`customers.view`, `products.view`, `models.view`, `returns.view`) não
 * entram nesta matriz: não há papel para testar a negação sem inventar um
 * papel que não existe no sistema.
 */
class PermissionDenialMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    public static function denialMatrixProvider(): array
    {
        return [
            // Clientes — vendedor só tem customers.view.
            'customers.create -> POST /customers (vendedor)' => ['post', '/api/customers', UserRole::Seller->value],
            'customers.update -> PATCH /customers/{id} (vendedor)' => ['patch', '/api/customers/999999', UserRole::Seller->value],
            'customers.delete -> DELETE /customers/{id} (vendedor)' => ['delete', '/api/customers/999999', UserRole::Seller->value],

            // Produtos — vendedor só tem products.view.
            'products.create -> POST /products (vendedor)' => ['post', '/api/products', UserRole::Seller->value],
            'products.update -> PATCH /products/{id} (vendedor)' => ['patch', '/api/products/999999', UserRole::Seller->value],
            'products.update -> PATCH /products/{id}/add-qty (vendedor)' => ['patch', '/api/products/999999/add-qty', UserRole::Seller->value],
            'products.delete -> DELETE /products/{id} (vendedor)' => ['delete', '/api/products/999999', UserRole::Seller->value],

            // Marcas — GET já coberto em AuthFlowTest; create/update/delete faltavam.
            'brands.create -> POST /brands (vendedor)' => ['post', '/api/brands', UserRole::Seller->value],
            'brands.update -> PATCH /brands/{id} (vendedor)' => ['patch', '/api/brands/999999', UserRole::Seller->value],
            'brands.delete -> DELETE /brands/{id} (vendedor)' => ['delete', '/api/brands/999999', UserRole::Seller->value],

            // Qualidades — nunca testado (nenhum arquivo cobre este controller).
            'qualities.view -> GET /qualities (vendedor)' => ['get', '/api/qualities', UserRole::Seller->value],
            'qualities.create -> POST /qualities (vendedor)' => ['post', '/api/qualities', UserRole::Seller->value],
            'qualities.update -> PATCH /qualities/{id} (vendedor)' => ['patch', '/api/qualities/999999', UserRole::Seller->value],
            'qualities.delete -> DELETE /qualities/{id} (vendedor)' => ['delete', '/api/qualities/999999', UserRole::Seller->value],

            // Categorias — view/create já cobertos em CategoryControllerTest;
            // update/delete faltavam.
            'categories.update -> PATCH /categories/{id} (vendedor)' => ['patch', '/api/categories/999999', UserRole::Seller->value],
            'categories.delete -> DELETE /categories/{id} (vendedor)' => ['delete', '/api/categories/999999', UserRole::Seller->value],

            // Modelos — vendedor tem models.view mas não create/update/delete.
            'models.create -> POST /models (vendedor)' => ['post', '/api/models', UserRole::Seller->value],
            'models.update -> PATCH /models/{id} (vendedor)' => ['patch', '/api/models/999999', UserRole::Seller->value],
            'models.delete -> DELETE /models/{id} (vendedor)' => ['delete', '/api/models/999999', UserRole::Seller->value],

            // Pedidos — garantia é o único papel sem orders.view. É
            // deliberadamente excluído da matriz financeira em
            // FinancialPermissionMatrixTest (comentário: fora de escopo
            // daquela task), mas isso deixou a negação de orders.view nunca
            // exercitada em lugar nenhum até este teste.
            'orders.view -> GET /orders (garantia)' => ['get', '/api/orders', UserRole::Guarantee->value],
            'orders.view -> GET /orders/metadata (garantia)' => ['get', '/api/orders/metadata', UserRole::Guarantee->value],
            // orders.delete nunca foi exercitado em nenhum teste (nem o
            // caminho feliz) — vendedor (tem view, não tem delete) é o papel
            // mais próximo entre os que vendem.
            'orders.delete -> DELETE /orders/{id} (vendedor)' => ['delete', '/api/orders/999999', UserRole::Seller->value],

            // Trocas/garantias — returns.create e returns.update (vendedor
            // negado) passaram a ser cobertos por ReturnControllerTest
            // (também TASK-015, escrito em paralelo) — não duplicamos aqui.
            // returns.delete continua sem cobertura em qualquer arquivo:
            // garantia é o papel mais próximo (tem view+create+update, só
            // falta delete — ninguém de vendas tem delete pra comparar).
            'returns.delete -> DELETE /returns/{id} (garantia)' => ['delete', '/api/returns/999999', UserRole::Guarantee->value],

            // Despesas gerais — expenses.view já tem matriz em
            // ExpenseControllerTest (gerente/vendedor/garantia negados);
            // create/update/delete nunca foram testados. Mantém-se gerente
            // pra ficar consistente com o teste de view já existente.
            'expenses.create -> POST /expenses (gerente)' => ['post', '/api/expenses', UserRole::Manager->value],
            'expenses.update -> PATCH /expenses/{id} (gerente)' => ['patch', '/api/expenses/999999', UserRole::Manager->value],
            'expenses.delete -> DELETE /expenses/{id} (gerente)' => ['delete', '/api/expenses/999999', UserRole::Manager->value],

            // Metas — nunca testado (nenhum arquivo cobre este controller).
            // Garantia é o único papel sem goals.view; vendedor tem
            // goals.view mas não create/update/delete.
            'goals.view -> GET /goals (garantia)' => ['get', '/api/goals', UserRole::Guarantee->value],
            'goals.view -> GET /goals/metadata (garantia)' => ['get', '/api/goals/metadata', UserRole::Guarantee->value],
            'goals.create -> POST /goals (vendedor)' => ['post', '/api/goals', UserRole::Seller->value],
            'goals.update -> PATCH /goals/{id} (vendedor)' => ['patch', '/api/goals/999999', UserRole::Seller->value],
            'goals.delete -> DELETE /goals/{id} (vendedor)' => ['delete', '/api/goals/999999', UserRole::Seller->value],

            // Usuários — FinancialPermissionMatrixTest já cobre gerente (que
            // TEM users.manage) sendo bloqueado por ownership ao tentar
            // afetar admin/owner. Faltava o papel que não tem users.manage
            // NENHUM (vendedor) sendo bloqueado pelo próprio middleware.
            'users.manage -> GET /users (vendedor)' => ['get', '/api/users', UserRole::Seller->value],
            'users.manage -> POST /users (vendedor)' => ['post', '/api/users', UserRole::Seller->value],
            'users.manage -> PATCH /users/{id} (vendedor)' => ['patch', '/api/users/999999', UserRole::Seller->value],
            'users.manage -> PATCH /users/{id}/active (vendedor)' => ['patch', '/api/users/999999/active', UserRole::Seller->value],
            'users.manage -> PATCH /users/{id}/password (vendedor)' => ['patch', '/api/users/999999/password', UserRole::Seller->value],
        ];
    }

    #[DataProvider('denialMatrixProvider')]
    public function test_role_without_permission_is_denied(string $method, string $uri, string $role): void
    {
        // IDs numéricos usados nas rotas com {id} não precisam existir: o
        // middleware `permission:` roda antes de qualquer resolução de
        // model/controller (os controllers recebem `int $id`, não fazem
        // model binding implícito), então a negação de permissão acontece
        // antes de qualquer tentativa de leitura no banco.
        $user = User::factory()->create(['role' => $role]);

        $request = $this->actingAs($user);
        if ($method !== 'get') {
            $request = $request->withSession(['_token' => 'csrf-token']);
        }

        $response = match ($method) {
            'get' => $request->getJson($uri),
            'post' => $request->postJson($uri, [], self::CSRF_HEADERS),
            'patch' => $request->patchJson($uri, [], self::CSRF_HEADERS),
            'put' => $request->putJson($uri, [], self::CSRF_HEADERS),
            'delete' => $request->deleteJson($uri, [], self::CSRF_HEADERS),
            default => throw new \InvalidArgumentException("Método não suportado: {$method}"),
        };

        $response->assertStatus(403);
    }
}
