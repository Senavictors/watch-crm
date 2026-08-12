<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\User;
use App\Support\ReturnItemResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDOException;
use Tests\TestCase;

/**
 * TASK-028 (CA-04 / RN-05) — duas devoluções concorrentes não ultrapassam o
 * saldo do item.
 *
 * A suíte padrão roda em SQLite, onde `lockForUpdate()` é um no-op: ela
 * prova a regra ("acima do saldo é recusado"), mas não a exclusão mútua.
 * Aqui, MySQL 8.4 real e duas conexões independentes — enquanto a primeira
 * transação segura as linhas do pedido, a segunda não consegue lê-las para
 * validar o próprio saldo.
 *
 * Mesmo banco descartável usado pelos outros testes de MySQL; o banco de dev
 * nunca é tocado. Sem MySQL alcançável, o teste é pulado.
 */
class ReturnBalanceConcurrencyMySqlTest extends TestCase
{
    private const DATABASE = 'watch_crm_stock_concurrency';

    private bool $databaseCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (env('DB_DATABASE') === self::DATABASE) {
            $this->fail('O banco descartável não pode ser o banco configurado da aplicação.');
        }

        $base = [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'mysql'),
            'port' => (int) env('DB_PORT', 3306),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ];

        config([
            'database.connections.returns_bootstrap' => $base + ['database' => 'information_schema'],
            'database.connections.returns_a' => $base + ['database' => self::DATABASE],
            'database.connections.returns_b' => $base + ['database' => self::DATABASE],
        ]);

        try {
            DB::connection('returns_bootstrap')->statement('CREATE DATABASE IF NOT EXISTS `'.self::DATABASE.'`');
        } catch (QueryException|PDOException $e) {
            $this->markTestSkipped('MySQL indisponível neste ambiente: '.$e->getMessage());
        }

        $this->databaseCreated = true;

        config(['database.default' => 'returns_a']);
        Artisan::call('migrate:fresh', ['--database' => 'returns_a', '--force' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->databaseCreated) {
            DB::disconnect('returns_a');
            DB::disconnect('returns_b');
            DB::connection('returns_bootstrap')->statement('DROP DATABASE IF EXISTS `'.self::DATABASE.'`');
            DB::disconnect('returns_bootstrap');
        }

        parent::tearDown();
    }

    public function test_duas_devolucoes_concorrentes_nao_ultrapassam_o_saldo(): void
    {
        $actor = User::factory()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['qty' => 10]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $actor->id,
            'seller_user_id' => $actor->id,
            'product_id' => $product->id,
            'product_name' => 'Relógio Vendido',
            'channel' => 'Instagram',
            'seller' => $actor->name,
            'status' => 'Pago',
            'sale_price' => 200,
            'cost' => 80,
            'discount' => 0,
            'freight' => 0,
            'channel_fee' => 0,
            'shipping_method' => 'Sedex',
            'sale_date' => now()->toDateString(),
            'paid_at' => now(),
        ]);

        // Uma única unidade vendida: só cabe uma devolução.
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Relógio Vendido',
            'product_type' => 'Relógios',
            'quantity' => 1,
            'unit_price' => 200,
            'unit_cost' => 80,
            'unit_discount' => 0,
        ]);

        $first = $this->makeReturn($order, $customer, $actor);
        $second = $this->makeReturn($order, $customer, $actor);

        $items = [['orderItemId' => $orderItem->id, 'quantity' => 1]];

        // --- Transação A: resolve o saldo e SEGURA o lock da linha. ---
        $connectionA = DB::connection('returns_a');
        $connectionA->beginTransaction();
        $rowsA = ReturnItemResolver::resolve($order, $items, $first->id);
        ReturnItem::insert(array_map(fn (array $row) => [
            ...$row,
            'return_id' => $first->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $rowsA));

        // --- Transação B: outra conexão, mesmo item. ---
        config(['database.default' => 'returns_b']);
        $connectionB = DB::connection('returns_b');
        $connectionB->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $connectionB->beginTransaction();

        $blocked = false;
        try {
            ReturnItemResolver::resolve($order, $items, $second->id);
        } catch (QueryException $e) {
            $blocked = true;
        }

        $connectionB->rollBack();

        $this->assertTrue(
            $blocked,
            'A segunda transação deveria ficar bloqueada pelo lock da primeira ao calcular o saldo.'
        );

        config(['database.default' => 'returns_a']);
        $connectionA->commit();

        // Com a primeira devolução persistida, o saldo acabou.
        config(['database.default' => 'returns_b']);
        $rejected = false;
        try {
            $connectionB->transaction(fn () => ReturnItemResolver::resolve($order, $items, $second->id));
        } catch (ValidationException $e) {
            $rejected = true;
            $this->assertStringContainsString('saldo devolvível', $e->getMessage());
        }

        $this->assertTrue($rejected, 'A segunda devolução da única unidade vendida precisa ser recusada.');

        config(['database.default' => 'returns_a']);
        $this->assertSame(1, ReturnItem::where('order_item_id', $orderItem->id)->count());
        $this->assertSame(0, ReturnItemResolver::returnableQuantity($orderItem->fresh()));
    }

    private function makeReturn(Order $order, Customer $customer, User $actor): ProductReturn
    {
        return ProductReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $actor->id,
            'type' => 'devolucao',
            'status' => 'Recebido',
        ]);
    }
}
