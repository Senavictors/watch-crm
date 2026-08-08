<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Support\AiConfiguration;
use Illuminate\Http\Request;

class AiSettingsController extends Controller
{
    public function show()
    {
        return response()->json(AiConfiguration::publicStatus());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'apiKey' => ['nullable', 'string', 'min:20', 'max:500'],
            'projectId' => ['nullable', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'enabled' => ['required', 'boolean'],
        ]);

        $setting = AiSetting::query()->firstOrNew(['provider' => 'openai']);
        $setting->model = $data['model'];
        $setting->project_id = filled($data['projectId'] ?? null) ? trim($data['projectId']) : null;
        $setting->enabled = $data['enabled'];
        $setting->updated_by_user_id = $request->user()->id;

        if (filled($data['apiKey'] ?? null)) {
            $setting->api_key = trim($data['apiKey']);
        }

        $setting->save();

        $this->audit('ai.settings_updated', 'Configuração do resumo inteligente atualizada.', $setting, [
            'provider' => 'openai',
            'model' => $setting->model,
            'enabled' => $setting->enabled,
            'has_project_id' => filled($setting->project_id),
            'api_key_replaced' => filled($data['apiKey'] ?? null),
        ]);

        return response()->json(AiConfiguration::publicStatus());
    }

    public function destroyKey(Request $request)
    {
        $setting = AiSetting::query()->first();
        if ($setting) {
            $setting->forceFill([
                'api_key' => null,
                'updated_by_user_id' => $request->user()->id,
            ])->save();

            $this->audit('ai.api_key_removed', 'Chave da OpenAI removida da configuração do CRM.', $setting);
        }

        return response()->noContent();
    }
}
