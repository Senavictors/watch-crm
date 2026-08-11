<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

/**
 * TASK-025 (CA-01/CA-05, ADR-007 item 6) — a garantia de BANCO.
 *
 * Os testes de aplicação provam que o controller recusa a exclusão. Este aqui
 * prova a segunda linha de defesa: mesmo um `DELETE` direto no banco (script,
 * console, bug futuro em outro caminho) não consegue mais varrer pedidos e
 * catálogo, porque as chaves estrangeiras deixaram de ser CASCADE.
 *
 * Roda contra MySQL 8.4 real num banco descartável — a suíte padrão é SQLite,
 * onde a migration não reescreve FK (ver comentário na própria migration).
 * Sem MySQL alcançável, o teste é pulado.
 */
class RestrictedDeletesMySqlTest extends TestCase
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
            'database.connections.restrict_bootstrap' => $base + ['database' => 'information_schema'],
            'database.connections.restrict_main' => $base + ['database' => self::DATABASE],
        ]);

        try {
            DB::connection('restrict_bootstrap')->statement('CREATE DATABASE IF NOT EXISTS `'.self::DATABASE.'`');
        } catch (QueryException|PDOException $e) {
            $this->markTestSkipped('MySQL indisponível neste ambiente: '.$e->getMessage());
        }

        $this->databaseCreated = true;

        config(['database.default' => 'restrict_main']);
        Artisan::call('migrate:fresh', ['--database' => 'restrict_main', '--force' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->databaseCreated) {
            DB::disconnect('restrict_main');
            DB::connection('restrict_bootstrap')->statement('DROP DATABASE IF EXISTS `'.self::DATABASE.'`');
            DB::disconnect('restrict_bootstrap');
        }

        parent::tearDown();
    }

    public function test_as_chaves_estrangeiras_de_historico_nao_sao_mais_cascata(): void
    {
        $rules = DB::connection('restrict_main')
            ->table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', self::DATABASE)
            ->whereIn('CONSTRAINT_NAME', [
                'orders_customer_id_foreign',
                'returns_customer_id_foreign',
                'customer_friction_notes_customer_id_foreign',
                'models_brand_id_foreign',
                'models_category_id_foreign',
                'models_quality_id_foreign',
                'products_brand_id_foreign',
                'products_model_id_foreign',
            ])
            ->pluck('DELETE_RULE', 'CONSTRAINT_NAME');

        $this->assertCount(8, $rules, 'Todas as FKs protegidas precisam existir.');

        foreach ($rules as $name => $rule) {
            $this->assertSame('RESTRICT', $rule, "{$name} ainda apaga em cascata.");
        }
    }

    public function test_delete_direto_de_cliente_com_pedido_e_recusado_pelo_banco(): void
    {
        $actor = User::factory()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        $order = Order::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $actor->id,
            'seller_user_id' => $actor->id,
            'product_id' => $product->id,
            'product_name' => 'Teste',
            'channel' => 'Instagram',
            'seller' => $actor->name,
            'status' => 'Pago',
            'sale_price' => 1000,
            'cost' => 400,
            'discount' => 0,
            'freight' => 0,
            'channel_fee' => 0,
            'shipping_method' => 'Sedex',
            'sale_date' => now()->toDateString(),
            'paid_at' => now(),
        ]);

        $blocked = false;
        try {
            DB::connection('restrict_main')->table('customers')->where('id', $customer->id)->delete();
        } catch (QueryException $e) {
            $blocked = true;
        }

        $this->assertTrue($blocked, 'O banco deveria recusar o DELETE do cliente com pedido.');
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_delete_direto_de_marca_com_modelo_e_recusado_pelo_banco(): void
    {
        $product = Product::factory()->create();
        $brandId = $product->brand_id;

        $blocked = false;
        try {
            DB::connection('restrict_main')->table('brands')->where('id', $brandId)->delete();
        } catch (QueryException $e) {
            $blocked = true;
        }

        $this->assertTrue($blocked, 'O banco deveria recusar o DELETE da marca em uso.');
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('models', ['id' => $product->model_id]);
        $this->assertSame(1, WatchModel::query()->where('brand_id', $brandId)->count());
        $this->assertSame(1, Brand::query()->where('id', $brandId)->count());
    }
}
