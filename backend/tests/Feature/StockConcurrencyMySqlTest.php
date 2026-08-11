<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\StockLedger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

/**
 * TASK-024 (CA-01) — prova de concorrência REAL no MySQL 8.4.
 *
 * A suíte padrão roda em SQLite in-memory, onde `lockForUpdate()` é um no-op:
 * ela consegue provar a regra ("a segunda venda é recusada"), mas não a
 * exclusão mútua. Este teste sobe um banco MySQL descartável, abre DUAS
 * conexões independentes e verifica que, enquanto a primeira transação segura
 * o produto, a segunda não consegue sequer ler a linha para modificá-la —
 * é o lock do InnoDB que serializa as duas vendas, não a sorte do
 * agendamento.
 *
 * Nunca toca o banco de dev: cria e derruba `watch_crm_stock_concurrency` e
 * aborta se esse nome coincidir com `DB_DATABASE`. Se não houver MySQL
 * alcançável (rodando fora do Docker, por exemplo), o teste é pulado.
 */
class StockConcurrencyMySqlTest extends TestCase
{
    private const DATABASE = 'watch_crm_stock_concurrency';

    private bool $databaseCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        $host = env('DB_HOST', 'mysql');
        $port = (int) env('DB_PORT', 3306);
        $username = env('DB_USERNAME', 'root');
        $password = env('DB_PASSWORD', '');

        if (env('DB_DATABASE') === self::DATABASE) {
            $this->fail('O banco descartável de concorrência não pode ser o banco configurado da aplicação.');
        }

        $base = [
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ];

        config([
            'database.connections.stock_bootstrap' => $base + ['database' => 'information_schema'],
            'database.connections.stock_a' => $base + ['database' => self::DATABASE],
            'database.connections.stock_b' => $base + ['database' => self::DATABASE],
        ]);

        try {
            DB::connection('stock_bootstrap')->statement('CREATE DATABASE IF NOT EXISTS `'.self::DATABASE.'`');
        } catch (QueryException|PDOException $e) {
            $this->markTestSkipped('MySQL indisponível neste ambiente: '.$e->getMessage());
        }

        $this->databaseCreated = true;

        config(['database.default' => 'stock_a']);
        Artisan::call('migrate:fresh', ['--database' => 'stock_a', '--force' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->databaseCreated) {
            DB::disconnect('stock_a');
            DB::disconnect('stock_b');
            DB::connection('stock_bootstrap')->statement('DROP DATABASE IF EXISTS `'.self::DATABASE.'`');
            DB::disconnect('stock_bootstrap');
        }

        parent::tearDown();
    }

    public function test_duas_vendas_concorrentes_da_ultima_unidade(): void
    {
        $actor = User::factory()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['qty' => 1, 'reserved_qty' => 0]);

        $first = $this->makeOrder($customer, $actor, $product);
        $second = $this->makeOrder($customer, $actor, $product);

        // --- Transação A: reserva a última unidade e SEGURA o lock. ---
        $connectionA = DB::connection('stock_a');
        $connectionA->beginTransaction();
        StockLedger::syncOrder($first->load('items'), $actor);

        // --- Transação B: outra conexão, mesma última unidade. ---
        config(['database.default' => 'stock_b']);
        $connectionB = DB::connection('stock_b');
        $connectionB->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $connectionB->beginTransaction();

        $blocked = false;
        try {
            StockLedger::syncOrder($second->load('items'), $actor);
        } catch (QueryException $e) {
            $blocked = true;
        }

        $connectionB->rollBack();

        $this->assertTrue(
            $blocked,
            'A segunda transação deveria ficar bloqueada pelo lock da primeira, não seguir em frente.'
        );

        // A confirma a venda; só então B enxerga o saldo real.
        config(['database.default' => 'stock_a']);
        $connectionA->commit();

        config(['database.default' => 'stock_b']);
        $rejected = false;
        try {
            $connectionB->transaction(fn () => StockLedger::syncOrder($second->load('items'), $actor));
        } catch (InsufficientStockException $e) {
            $rejected = true;
            $this->assertSame(0, $e->available);
        }

        $this->assertTrue($rejected, 'A segunda venda da última unidade precisa ser recusada.');

        config(['database.default' => 'stock_a']);
        $product->refresh();
        $this->assertSame(1, $product->qty);
        $this->assertSame(1, $product->reserved_qty);
        $this->assertSame(0, $product->availableQty());

        $this->assertSame(1, StockReservation::where('status', StockReservation::STATUS_RESERVED)->count());
        $this->assertSame($first->id, (int) StockReservation::first()->order_id);
    }

    private function makeOrder(Customer $customer, User $actor, Product $product): Order
    {
        $order = Order::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $actor->id,
            'seller_user_id' => $actor->id,
            'product_id' => $product->id,
            'product_name' => $product->displayLabel(),
            'channel' => 'Instagram',
            'seller' => $actor->name,
            'status' => 'Novo',
            'sale_price' => 200,
            'cost' => 100,
            'discount' => 0,
            'freight' => 0,
            'channel_fee' => 0,
            'shipping_method' => 'Sedex',
            'sale_date' => now()->toDateString(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->displayLabel(),
            'product_type' => 'Relógios',
            'quantity' => 1,
            'unit_price' => 200,
            'unit_cost' => 100,
            'unit_discount' => 0,
        ]);

        return $order;
    }
}
