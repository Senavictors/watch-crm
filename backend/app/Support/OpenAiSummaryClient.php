<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use UnexpectedValueException;

class OpenAiSummaryClient
{
    public function selectFacts(array $configuration, array $facts): array
    {
        $allowedIds = array_keys($facts);
        $headers = [];
        if (filled($configuration['projectId'])) {
            $headers['OpenAI-Project'] = $configuration['projectId'];
        }

        $schema = [
            'type' => 'object',
            'properties' => [
                'factIds' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 5,
                    'items' => ['type' => 'string', 'enum' => $allowedIds],
                ],
            ],
            'required' => ['factIds'],
            'additionalProperties' => false,
        ];

        $externalFacts = collect($facts)->map(fn (array $fact) => [
            'id' => $fact['id'],
            'statement' => $fact['text'],
            'sources' => $fact['sources'],
        ])->values()->all();

        try {
            $response = Http::withToken($configuration['apiKey'])
                ->withHeaders($headers)
                ->acceptJson()
                ->timeout((int) config('services.openai.summary_timeout', 20))
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $configuration['model'],
                    'store' => false,
                    'reasoning' => ['effort' => 'none'],
                    'max_output_tokens' => 250,
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => 'Selecione de 3 a 5 fatos mais relevantes para um resumo operacional diário. Retorne somente IDs fornecidos. Não crie fatos, números, recomendações ou previsões.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode(['facts' => $externalFacts], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        ],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'operation_summary_selection',
                            'strict' => true,
                            'schema' => $schema,
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Falha de conexão com o provedor de IA.', previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('O provedor de IA retornou HTTP '.$response->status().'.');
        }

        $outputText = collect($response->json('output', []))
            ->where('type', 'message')
            ->flatMap(fn (array $item) => $item['content'] ?? [])
            ->firstWhere('type', 'output_text');
        $text = is_array($outputText) ? ($outputText['text'] ?? null) : null;
        if (! is_string($text)) {
            throw new UnexpectedValueException('O provedor não retornou uma seleção estruturada.');
        }

        $decoded = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        $selected = AiSummarySelectionValidator::validate($decoded['factIds'] ?? null, $allowedIds);

        return [
            'factIds' => $selected,
            'usage' => [
                'inputTokens' => (int) data_get($response->json(), 'usage.input_tokens', 0),
                'outputTokens' => (int) data_get($response->json(), 'usage.output_tokens', 0),
                'totalTokens' => (int) data_get($response->json(), 'usage.total_tokens', 0),
            ],
            'requestId' => $response->header('x-request-id'),
        ];
    }
}
