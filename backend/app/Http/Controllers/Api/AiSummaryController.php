<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AiSummaryService;
use App\Support\DashboardPeriodResolver;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class AiSummaryController extends Controller
{
    public function cached(Request $request, AiSummaryService $service)
    {
        $period = $this->period($request);
        if ($period instanceof \Illuminate\Http\JsonResponse) {
            return $period;
        }

        $cached = $service->cached($request->user(), $period);

        return $cached ? response()->json($cached) : response()->noContent();
    }

    public function generate(Request $request, AiSummaryService $service)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'refresh' => ['sometimes', 'boolean'],
        ]);

        try {
            $period = DashboardPeriodResolver::resolve($data['from'] ?? null, $data['to'] ?? null);
            $summary = $service->generate($request->user(), $period, (bool) ($data['refresh'] ?? false));

            return response()->json($summary);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException) {
            return response()->json(['message' => 'Resumo indisponível.'], 503);
        }
    }

    private function period(Request $request): mixed
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        try {
            return DashboardPeriodResolver::resolve($request->input('from'), $request->input('to'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
