<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\WatchModel;
use App\Support\ApiPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ModelController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->listQuery();
        $this->applySearch($query, $request->string('search')->toString());
        $models = $query->orderByDesc('id')->paginate(ApiPagination::perPage($request));

        return ApiPagination::response($models, fn (WatchModel $model) => $this->toPayload($model));
    }

    public function lookup(Request $request)
    {
        $query = $this->listQuery();
        $this->applySearch($query, $request->string('search')->toString());
        $models = $query->orderBy('name')->limit(20)->get();

        return response()->json(['data' => $models->map(fn (WatchModel $model) => $this->toPayload($model))->values()]);
    }

    private function listQuery()
    {
        return WatchModel::query()->with(['brand', 'category', 'quality', 'products']);
    }

    private function applySearch($query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $query->where(function ($builder) use ($search) {
            $builder->where('name', 'like', "%{$search}%")
                ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', "%{$search}%"))
                ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"))
                ->orWhereHas('quality', fn ($quality) => $quality->where('name', 'like', "%{$search}%"));
        });
    }

    public function store(Request $request)
    {
        $categoryId = $request->input('categoryId');
        $hasQuality = $this->categoryHasQuality($categoryId);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('models', 'name')
                    ->where('brand_id', $request->input('brandId'))
                    ->where('category_id', $categoryId)
                    ->where('quality_key', $this->qualityKey($hasQuality, $request->input('qualityId'))),
            ],
            'brandId' => ['required', 'integer', 'exists:brands,id'],
            'categoryId' => ['required', 'integer', 'exists:categories,id'],
            'qualityId' => [
                Rule::requiredIf($hasQuality),
                Rule::prohibitedIf(! $hasQuality),
                'nullable',
                'integer',
                'exists:qualities,id',
            ],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('models', 'public');
        }

        $model = WatchModel::create([
            'name' => $data['name'],
            'brand_id' => $data['brandId'],
            'category_id' => $data['categoryId'],
            'quality_id' => $hasQuality ? $data['qualityId'] : null,
            'quality_key' => $this->qualityKey($hasQuality, $data['qualityId'] ?? null),
            'image_path' => $imagePath,
        ]);
        $model->load(['brand', 'category', 'quality']);
        $this->audit('models.created', 'Modelo criado.', $model);

        return response()->json($this->toPayload($model), 201);
    }

    public function update(Request $request, int $id)
    {
        $model = WatchModel::find($id);

        if (! $model) {
            return response()->json(['message' => 'Modelo não encontrado.'], 404);
        }

        $brandId = $request->input('brandId', $model->brand_id);
        $categoryId = $request->input('categoryId', $model->category_id);
        $hasQuality = $this->categoryHasQuality($categoryId);
        $qualityId = $hasQuality
            ? $request->input('qualityId', $model->quality_id)
            : null;

        $data = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('models', 'name')
                    ->where('brand_id', $brandId)
                    ->where('category_id', $categoryId)
                    ->where('quality_key', $this->qualityKey($hasQuality, $qualityId))
                    ->ignore($model->id),
            ],
            'brandId' => ['sometimes', 'integer', 'exists:brands,id'],
            'categoryId' => ['sometimes', 'integer', 'exists:categories,id'],
            'qualityId' => [
                'sometimes',
                Rule::prohibitedIf(! $hasQuality),
                'nullable',
                'integer',
                'exists:qualities,id',
            ],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($request->hasFile('image')) {
            if ($model->image_path) {
                Storage::disk('public')->delete($model->image_path);
            }
            $model->image_path = $request->file('image')->store('models', 'public');
        }

        if (array_key_exists('brandId', $data)) {
            $data['brand_id'] = $data['brandId'];
            unset($data['brandId']);
        }
        if (array_key_exists('qualityId', $data)) {
            $data['quality_id'] = $data['qualityId'];
            unset($data['qualityId']);
        }
        if (array_key_exists('categoryId', $data)) {
            $data['category_id'] = $data['categoryId'];
            unset($data['categoryId']);
        }

        $resolvedCategoryId = $data['category_id'] ?? $model->category_id;
        $resolvedHasQuality = $this->categoryHasQuality($resolvedCategoryId);
        if (
            $resolvedHasQuality
            && (($data['quality_id'] ?? $model->quality_id) === null)
        ) {
            return response()->json([
                'message' => 'A qualidade é obrigatória para esta categoria.',
                'errors' => [
                    'qualityId' => ['A qualidade é obrigatória para esta categoria.'],
                ],
            ], 422);
        }

        $data['quality_id'] = $resolvedHasQuality
            ? ($data['quality_id'] ?? $model->quality_id)
            : null;
        $data['quality_key'] = $this->qualityKey($resolvedHasQuality, $data['quality_id']);

        $model->fill($data);
        $model->save();
        $model->load(['brand', 'category', 'quality']);
        $this->audit('models.updated', 'Modelo atualizado.', $model);

        return response()->json($this->toPayload($model));
    }

    public function destroy(int $id)
    {
        $model = WatchModel::find($id);

        if (! $model) {
            return response()->json(['message' => 'Modelo não encontrado.'], 404);
        }

        // TASK-025: FK era CASCADE em `products` — apagar o modelo apagava
        // as entradas de estoque dele.
        $conflict = $this->conflictIfInUse([
            'produtos' => Product::query()->where('model_id', $model->id)->count(),
        ], 'Este modelo', 'model_in_use');

        if ($conflict) {
            return $conflict;
        }

        DB::transaction(function () use ($model, $id) {
            $model->delete();
            $this->audit('models.deleted', 'Modelo removido.', null, ['model_id' => $id]);
        });

        return response()->json(['ok' => true]);
    }

    private function toPayload(WatchModel $model): array
    {
        $products = $model->relationLoaded('products') ? $model->products : collect();

        return [
            'id' => $model->id,
            'brandId' => $model->brand_id,
            'brandName' => $model->brand?->name,
            'name' => $model->name,
            'categoryId' => $model->category_id,
            'categoryName' => $model->category?->name,
            'categoryHasQuality' => (bool) $model->category?->has_quality,
            'qualityId' => $model->quality_id,
            'qualityName' => $model->quality?->name,
            'imageUrl' => $model->image_path ? url(Storage::url($model->image_path)) : null,
            'qtyInStock' => $products->where('stock', 'IN_STOCK')->sum('qty'),
            'qtyAtSupplier' => $products->where('stock', 'SUPPLIER')->sum('qty'),
        ];
    }

    private function categoryHasQuality(mixed $categoryId): bool
    {
        if (! $categoryId) {
            return false;
        }

        return (bool) Category::find($categoryId)?->has_quality;
    }

    private function qualityKey(bool $hasQuality, mixed $qualityId): int
    {
        if (! $hasQuality) {
            return 0;
        }

        return (int) $qualityId;
    }
}
