<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiPagination
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    public static function perPage(Request $request): int
    {
        return min(max($request->integer('perPage', self::DEFAULT_PER_PAGE), 1), self::MAX_PER_PAGE);
    }

    /**
     * @param  callable(mixed): array  $transform
     * @param  array<string, mixed>  $extra
     */
    public static function response(LengthAwarePaginator $paginator, callable $transform, array $extra = []): JsonResponse
    {
        return response()->json([
            ...$extra,
            'data' => collect($paginator->items())->map($transform)->values(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }
}
