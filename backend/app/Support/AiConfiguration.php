<?php

namespace App\Support;

use App\Models\AiSetting;
use Illuminate\Contracts\Encryption\DecryptException;

class AiConfiguration
{
    public static function resolve(): array
    {
        $setting = AiSetting::query()->first();
        $databaseKey = null;

        if ($setting?->getRawOriginal('api_key')) {
            try {
                $databaseKey = $setting->api_key;
            } catch (DecryptException) {
                // APP_KEY trocado ou valor legado inválido: nunca vazar a
                // exceção/segredo. O painel permitirá substituir a chave.
                $databaseKey = null;
            }
        }

        $environmentKey = trim((string) config('services.openai.api_key'));
        $apiKey = filled($databaseKey) ? trim((string) $databaseKey) : $environmentKey;
        $source = filled($databaseKey) ? 'database' : (filled($environmentKey) ? 'environment' : 'none');

        return [
            'featureEnabled' => (bool) config('services.openai.summary_enabled', true),
            'enabled' => $setting?->enabled ?? true,
            'configured' => filled($apiKey),
            'apiKey' => $apiKey,
            'apiKeySource' => $source,
            'model' => $setting?->model ?: (string) config('services.openai.model', 'gpt-5.6-luna'),
            'projectId' => $setting?->project_id ?: (config('services.openai.project') ?: null),
        ];
    }

    public static function publicStatus(): array
    {
        $config = self::resolve();

        return [
            'provider' => 'openai',
            'model' => $config['model'],
            'projectId' => $config['projectId'],
            'enabled' => $config['enabled'],
            'featureEnabled' => $config['featureEnabled'],
            'configured' => $config['configured'],
            'apiKeySource' => $config['apiKeySource'],
        ];
    }
}
