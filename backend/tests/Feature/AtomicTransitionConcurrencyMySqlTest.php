<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\LockedTransaction;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

/**
 * TASK-029 (CA-01/CA-05) — exclusão mútua real em MySQL 8.4 para pagamento,
 * devolução e lista de espera.
 *
 * A suíte padrão roda em SQLite, onde `lockForUpdate()` é um no-op: ela
 * prova as regras, não a serialização. Aqui, duas conexões independentes —
 * enquanto a primeira transação segura o registro, a segunda não consegue
 * relê-lo para decidir a própria transição, que é exatamente o que impedia
 * o lost update e o histórico impossível do achado 6.
 *
 * Usa o mesmo banco descartável dos outros testes de MySQL; o banco de dev
 * nunca é tocado. Sem MySQL alcançável, o teste é pulado.
 */
class AtomicTransitionConcurrencyMySqlTest extends TestCase
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
            'database.connections.atomic_bootstrap' => $base + ['database' => 'information_schema'],
            'database.connections.atomic_a' => $base + ['database' => self::DATABASE],
            'database.connections.atomic_b' => $base + ['database' => self::DATABASE],
        ]);

        try {
            DB::connection('atomic_bootstrap')->statement('CREATE DATABASE IF NOT EXISTS `'.self::DATABASE.'`');
        } catch (QueryException|PDOException $e) {
            $this->markTestSkipped('MySQL indisponível neste ambiente: '.$e->getMessage());
        }

        $this->databaseCreated = true;

        config(['database.default' => 'atomic_a']);
        Artisan::call('migrate:fresh', ['--database' => 'atomic_a', '--force' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->databaseCreated) {
            DB::disconnect('atomic_a');
            DB::disconnect('atomic_b');
            DB::connection('atomic_bootstrap')->statement('DROP DATABASE IF EXISTS `'.self::DATABASE.'`');
            DB::disconnect('atomic_bootstrap');
        }

        parent::tearDown();
    }

    /**
     * Núcleo compartilhado: a transação A segura o registro e o altera; a
     * transação B, em outra conexão, tenta relê-lo sob lock e precisa ficar
     * bloqueada. Depois do commit de A, B enxerga o estado NOVO — que é o
     * que impede a segunda requisição de decidir a partir de um estado que
     * já não existe.
     *
     * `$newQuery` é uma FÁBRICA, não um builder pronto: o Eloquent amarra a
     * conexão no momento em que a consulta é construída, então reaproveitar
     * o mesmo builder faria a "segunda transação" rodar na conexão da
     * primeira — e ninguém bloqueia o próprio lock.
     *
     * @param  callable(): \Illuminate\Database\Eloquent\Builder  $newQuery
     * @param  callable(Model): void  $mutate  o que A faz com o registro
     * @param  callable(Model): mixed  $readState  estado observado por B depois do commit
     */
    private function assertSerializes(
        callable $newQuery,
        int $id,
        callable $mutate,
        callable $readState,
        mixed $expectedAfterCommit,
        string $label,
    ): void {
        $connectionA = DB::connection('atomic_a');
        $connectionA->beginTransaction();

        $locked = $newQuery()->whereKey($id)->lockForUpdate()->firstOrFail();
        $mutate($locked);

        config(['database.default' => 'atomic_b']);
        $connectionB = DB::connection('atomic_b');
        $connectionB->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $connectionB->beginTransaction();

        $blocked = false;
        try {
            LockedTransaction::run($newQuery(), $id, fn (Model $record) => $record, 1);
        } catch (QueryException|DeadlockException $e) {
            $blocked = true;
        }

        $connectionB->rollBack();

        $this->assertTrue($blocked, "{$label}: a segunda transação deveria ficar bloqueada pelo lock da primeira.");

        config(['database.default' => 'atomic_a']);
        $connectionA->commit();

        // B agora lê o estado já atualizado, não o anterior.
        config(['database.default' => 'atomic_b']);
        $observed = $connectionB->transaction(fn () => $readState(
            $newQuery()->whereKey($id)->lockForUpdate()->firstOrFail()
        ));

        $this->assertSame(
            $expectedAfterCommit,
            $observed,
            "{$label}: a segunda transação precisa enxergar o estado novo, não o anterior."
        );

        config(['database.default' => 'atomic_a']);
    }

    public function test_confirmacao_de_pagamento_concorrente_e_serializada(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'Aguardando Pagamento',
            'paid_at' => null,
        ]);

        $this->assertSerializes(
            fn () => Order::query(),
            $order->id,
            function (Order $locked) {
                $locked->status = 'Pago';
                $locked->paid_at = now();
                $locked->save();
            },
            fn (Order $observed) => $observed->status,
            'Pago',
            'pagamento',
        );
    }

    public function test_transicao_de_devolucao_concorrente_e_serializada(): void
    {
        $customer = Customer::factory()->create();
        $actor = User::factory()->create();

        $productReturn = ProductReturn::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $actor->id,
            'type' => 'devolucao',
            'status' => 'Recebido',
        ]);

        $this->assertSerializes(
            fn () => ProductReturn::query(),
            $productReturn->id,
            function (ProductReturn $locked) {
                $locked->status = 'Em Análise';
                $locked->save();
            },
            fn (ProductReturn $observed) => $observed->status,
            'Em Análise',
            'devolução',
        );
    }

    public function test_atualizacao_de_lista_de_espera_concorrente_e_serializada(): void
    {
        $customer = Customer::factory()->create();
        $actor = User::factory()->create();

        $entry = WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_name' => 'Relógio X',
            'seller_user_id' => $actor->id,
            'created_by_user_id' => $actor->id,
            'status' => 'Pendente',
        ]);

        $this->assertSerializes(
            fn () => WaitlistEntry::query(),
            $entry->id,
            function (WaitlistEntry $locked) {
                $locked->status = 'Encerrado';
                $locked->save();
            },
            fn (WaitlistEntry $observed) => $observed->status,
            'Encerrado',
            'lista de espera',
        );
    }
}
