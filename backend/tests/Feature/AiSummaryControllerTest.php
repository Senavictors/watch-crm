<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AiSetting;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiSummaryControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.summary_enabled', true);
        config()->set('services.openai.api_key', null);
        config()->set('services.openai.project', null);
        config()->set('services.openai.model', 'gpt-5.6-luna');
        Cache::clear();
    }

    public static function pilotRoleProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value, true],
            'admin' => [UserRole::Admin->value, true],
            'gerente' => [UserRole::Manager->value, false],
            'vendedor' => [UserRole::Seller->value, false],
            'garantia' => [UserRole::Guarantee->value, false],
        ];
    }

    #[DataProvider('pilotRoleProvider')]
    public function test_summary_permission_is_restricted_to_pilot_roles(string $role, bool $allowed): void
    {
        $user = User::factory()->create(['role' => $role]);
        $response = $this->actingAs($user)->getJson('/api/ai/summary?from=2026-08-01&to=2026-08-31');

        $allowed ? $response->assertNoContent() : $response->assertForbidden();
    }

    #[DataProvider('pilotRoleProvider')]
    public function test_settings_permission_is_restricted_to_pilot_roles(string $role, bool $allowed): void
    {
        $user = User::factory()->create(['role' => $role]);
        $response = $this->actingAs($user)->getJson('/api/ai/settings');

        $allowed ? $response->assertOk() : $response->assertForbidden();
    }

    #[DataProvider('pilotRoleProvider')]
    public function test_all_ai_permissions_are_exposed_only_to_pilot_roles(string $role, bool $allowed): void
    {
        $user = User::factory()->create(['role' => $role]);
        $permissions = $this->actingAs($user)->getJson('/api/me')->assertOk()->json('user.permissions');

        foreach (['ai.summary.generate', 'ai.settings.view', 'ai.settings.update'] as $permission) {
            $this->assertSame($allowed, in_array($permission, $permissions, true), "role={$role} permission={$permission}");
        }
    }

    public function test_api_key_is_encrypted_at_rest_never_returned_and_audit_has_no_secret(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $secret = 'sk-proj-sentinel-secret-1234567890';

        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->putJson('/api/ai/settings', [
                'apiKey' => $secret,
                'projectId' => 'proj_watch_crm',
                'model' => 'gpt-5.6-luna',
                'enabled' => true,
            ], self::CSRF_HEADERS)
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('apiKeySource', 'database');

        $this->assertStringNotContainsString($secret, $response->getContent());
        $this->assertNotSame($secret, DB::table('ai_settings')->value('api_key'));
        $this->assertSame($secret, AiSetting::query()->firstOrFail()->api_key);

        $status = $this->actingAs($admin)->getJson('/api/ai/settings')->assertOk();
        $this->assertArrayNotHasKey('apiKey', $status->json());
        $this->assertStringNotContainsString($secret, $status->getContent());

        $audit = AuditLog::query()->where('action', 'ai.settings_updated')->firstOrFail();
        $this->assertStringNotContainsString($secret, json_encode($audit->metadata, JSON_THROW_ON_ERROR));
    }

    public function test_external_payload_contains_no_personal_data_and_response_uses_only_backend_facts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create([
            'name' => 'PESSOA-SENTINELA-NAO-ENVIAR',
            'phone' => '11999998888',
            'email' => 'sentinela-privada@example.test',
            'instagram' => '@sentinela_privada',
            'street' => 'Rua Privada Sentinela',
        ]);
        Order::factory()->paid($admin)->create([
            'customer_id' => $customer->id,
            'seller_user_id' => $admin->id,
            'paid_at' => '2026-08-08 12:00:00',
            'status' => 'Pago',
            'sale_price' => 250,
            'cost' => 100,
            'discount' => 0,
            'freight' => 0,
        ]);
        AiSetting::query()->create([
            'api_key' => 'sk-proj-test-key-1234567890',
            'model' => 'gpt-5.6-luna',
            'enabled' => true,
            'updated_by_user_id' => $admin->id,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'financial.revenue',
                'shipping.queue',
                'returns.open',
            ]), 200, ['x-request-id' => 'req_summary_test']),
        ]);

        $response = $this->postSummary($admin)
            ->assertOk()
            ->assertJsonCount(3, 'items')
            ->assertJsonPath('items.0.id', 'financial.revenue')
            ->assertJsonPath('model', 'gpt-5.6-luna');

        $this->assertStringContainsString('R$ 250,00', $response->json('items.0.text'));

        Http::assertSent(function ($request) {
            $body = $request->body();

            $this->assertFalse((bool) data_get($request->data(), 'store'));
            $this->assertSame('gpt-5.6-luna', data_get($request->data(), 'model'));
            $this->assertStringNotContainsString('PESSOA-SENTINELA-NAO-ENVIAR', $body);
            $this->assertStringNotContainsString('11999998888', $body);
            $this->assertStringNotContainsString('sentinela-privada@example.test', $body);
            $this->assertStringNotContainsString('@sentinela_privada', $body);
            $this->assertStringNotContainsString('Rua Privada Sentinela', $body);

            return true;
        });
    }

    public function test_cached_generation_does_not_call_provider_twice(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        AiSetting::query()->create([
            'api_key' => 'sk-proj-cache-test-1234567890',
            'model' => 'gpt-5.6-luna',
            'enabled' => true,
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'financial.revenue',
                'shipping.queue',
                'returns.open',
            ])),
        ]);

        $this->postSummary($admin)->assertOk()->assertJsonPath('cached', false);
        $this->postSummary($admin)->assertOk()->assertJsonPath('cached', true);
        $this->actingAs($admin)
            ->getJson('/api/ai/summary?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('cached', true);

        Http::assertSentCount(1);
    }

    public function test_provider_failure_does_not_affect_regular_dashboard(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        AiSetting::query()->create([
            'api_key' => 'sk-proj-failure-test-1234567890',
            'model' => 'gpt-5.6-luna',
            'enabled' => true,
        ]);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'external']], 500)]);

        $this->postSummary($admin)
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Resumo indisponível.']);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/summary?from=2026-08-01&to=2026-08-31')
            ->assertOk();
    }

    public function test_unconfigured_provider_returns_generic_unavailable_message(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner->value]);

        $this->postSummary($owner)
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Resumo indisponível.']);
    }

    private function postSummary(User $user, bool $refresh = false)
    {
        return $this->actingAs($user)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/ai/summary', [
                'from' => '2026-08-01',
                'to' => '2026-08-31',
                'refresh' => $refresh,
            ], self::CSRF_HEADERS);
    }

    private function openAiResponse(array $factIds): array
    {
        return [
            'id' => 'resp_test',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode(['factIds' => $factIds], JSON_THROW_ON_ERROR),
                ]],
            ]],
            'usage' => [
                'input_tokens' => 500,
                'output_tokens' => 20,
                'total_tokens' => 520,
            ],
        ];
    }
}
