<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AiSummaryService
{
    public function cached(User $user, DashboardPeriod $period): ?array
    {
        $configuration = AiConfiguration::resolve();
        if (! $configuration['featureEnabled'] || ! $configuration['enabled'] || ! $configuration['configured']) {
            return null;
        }

        $context = AiOperationContextBuilder::build($user, $period);
        $cached = Cache::get($this->cacheKey($user, $period, $context['facts'], $configuration['model']));

        return is_array($cached) ? [...$cached, 'cached' => true] : null;
    }

    public function generate(User $user, DashboardPeriod $period, bool $refresh = false): array
    {
        $configuration = AiConfiguration::resolve();
        if (! $configuration['featureEnabled'] || ! $configuration['enabled'] || ! $configuration['configured']) {
            Log::warning('ai_summary.failed', [
                'user_id' => $user->id,
                'model' => $configuration['model'],
                'duration_ms' => 0,
                'error_type' => 'configuration_unavailable',
            ]);
            throw new RuntimeException('Resumo inteligente não configurado ou desabilitado.');
        }

        $context = AiOperationContextBuilder::build($user, $period);
        $cacheKey = $this->cacheKey($user, $period, $context['facts'], $configuration['model']);

        if (! $refresh && is_array($cached = Cache::get($cacheKey))) {
            Log::info('ai_summary.cache_hit', ['user_id' => $user->id, 'model' => $cached['model'] ?? null]);

            return [...$cached, 'cached' => true];
        }

        $startedAt = hrtime(true);

        try {
            $selection = (new OpenAiSummaryClient)->selectFacts($configuration, $context['facts']);
            $items = collect($selection['factIds'])
                ->map(fn (string $id) => $context['facts'][$id])
                ->values()
                ->all();

            $payload = [
                'items' => $items,
                'period' => $context['period'],
                'generatedAt' => $context['snapshotAt'],
                'model' => $configuration['model'],
                'cached' => false,
            ];

            Cache::put($cacheKey, $payload, max((int) config('services.openai.summary_cache_ttl', 900), 1));

            Log::info('ai_summary.succeeded', [
                'user_id' => $user->id,
                'model' => $configuration['model'],
                'duration_ms' => $this->durationMs($startedAt),
                'input_tokens' => $selection['usage']['inputTokens'],
                'output_tokens' => $selection['usage']['outputTokens'],
                'total_tokens' => $selection['usage']['totalTokens'],
                'request_id' => $selection['requestId'],
            ]);

            return $payload;
        } catch (Throwable $e) {
            $rootCause = $this->rootCause($e);

            Log::warning('ai_summary.failed', [
                'user_id' => $user->id,
                'model' => $configuration['model'],
                'duration_ms' => $this->durationMs($startedAt),
                'error_type' => $e::class,
                'root_error_type' => $rootCause::class,
                'error_code' => $this->safeErrorCode($rootCause),
                'proxy_enabled' => filled(config('services.openai.proxy')),
            ]);

            throw new RuntimeException('Resumo indisponível.', previous: $e);
        }
    }

    private function cacheKey(User $user, DashboardPeriod $period, array $facts, string $model): string
    {
        $fingerprint = hash('sha256', json_encode([
            'period' => [$period->from, $period->to],
            'model' => $model,
            'facts' => $facts,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return 'ai_summary:v1:user:'.$user->id.':'.$fingerprint;
    }

    private function durationMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    private function rootCause(Throwable $exception): Throwable
    {
        while ($exception->getPrevious() instanceof Throwable) {
            $exception = $exception->getPrevious();
        }

        return $exception;
    }

    private function safeErrorCode(Throwable $exception): string
    {
        if (preg_match('/cURL error (\d+):/', $exception->getMessage(), $matches) === 1) {
            return 'curl_'.$matches[1];
        }

        if (preg_match('/HTTP (\d{3})\./', $exception->getMessage(), $matches) === 1) {
            return 'http_'.$matches[1];
        }

        return 'unknown';
    }
}
