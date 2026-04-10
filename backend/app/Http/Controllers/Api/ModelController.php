<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WatchModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ModelController extends Controller
{
    public function index()
    {
        $models = WatchModel::query()
            ->with(['brand', 'quality'])
            ->orderBy('id')
            ->get()
            ->map(fn (WatchModel $model) => $this->toPayload($model));

        return response()->json($models);
    }

    public function store(Request $request)
    {
        $productType = $request->input('productType', WatchModel::TYPE_WATCH);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('models', 'name')
                    ->where('brand_id', $request->input('brandId'))
                    ->where('product_type', $productType)
                    ->where('quality_key', $this->qualityKey($productType, $request->input('qualityId'))),
            ],
            'brandId' => ['required', 'integer', 'exists:brands,id'],
            'productType' => ['required', 'string', Rule::in([WatchModel::TYPE_WATCH, WatchModel::TYPE_BOX])],
            'qualityId' => [
                Rule::requiredIf($productType === WatchModel::TYPE_WATCH),
                Rule::prohibitedIf($productType === WatchModel::TYPE_BOX),
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
            'product_type' => $data['productType'],
            'quality_id' => $data['productType'] === WatchModel::TYPE_WATCH ? $data['qualityId'] : null,
            'quality_key' => $this->qualityKey($data['productType'], $data['qualityId'] ?? null),
            'image_path' => $imagePath,
        ]);
        $model->load(['brand', 'quality']);
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
        $productType = $request->input('productType', $model->product_type);
        $qualityId = $productType === WatchModel::TYPE_WATCH
            ? $request->input('qualityId', $model->quality_id)
            : null;

        $data = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('models', 'name')
                    ->where('brand_id', $brandId)
                    ->where('product_type', $productType)
                    ->where('quality_key', $this->qualityKey($productType, $qualityId))
                    ->ignore($model->id),
            ],
            'brandId' => ['sometimes', 'integer', 'exists:brands,id'],
            'productType' => ['sometimes', 'string', Rule::in([WatchModel::TYPE_WATCH, WatchModel::TYPE_BOX])],
            'qualityId' => [
                'sometimes',
                Rule::prohibitedIf($productType === WatchModel::TYPE_BOX),
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
        if (array_key_exists('productType', $data)) {
            $data['product_type'] = $data['productType'];
            unset($data['productType']);
        }

        $resolvedType = $data['product_type'] ?? $model->product_type;
        if (
            $resolvedType === WatchModel::TYPE_WATCH
            && (($data['quality_id'] ?? $model->quality_id) === null)
        ) {
            return response()->json([
                'message' => 'A qualidade é obrigatória para relógios.',
                'errors' => [
                    'qualityId' => ['A qualidade é obrigatória para relógios.'],
                ],
            ], 422);
        }

        $data['quality_id'] = $resolvedType === WatchModel::TYPE_WATCH
            ? ($data['quality_id'] ?? $model->quality_id)
            : null;
        $data['quality_key'] = $this->qualityKey($resolvedType, $data['quality_id']);

        $model->fill($data);
        $model->save();
        $model->load(['brand', 'quality']);
        $this->audit('models.updated', 'Modelo atualizado.', $model);

        return response()->json($this->toPayload($model));
    }

    public function destroy(int $id)
    {
        $model = WatchModel::find($id);

        if (! $model) {
            return response()->json(['message' => 'Modelo não encontrado.'], 404);
        }

        $model->delete();
        $this->audit('models.deleted', 'Modelo removido.', null, ['model_id' => $id]);

        return response()->json(['ok' => true]);
    }

    private function toPayload(WatchModel $model): array
    {
        return [
            'id' => $model->id,
            'brandId' => $model->brand_id,
            'brandName' => $model->brand?->name,
            'name' => $model->name,
            'productType' => $model->product_type,
            'qualityId' => $model->quality_id,
            'qualityName' => $model->quality?->name,
            'imageUrl' => $model->image_path ? url(Storage::url($model->image_path)) : null,
        ];
    }

    private function qualityKey(string $productType, mixed $qualityId): int
    {
        if ($productType === WatchModel::TYPE_BOX) {
            return 0;
        }

        return (int) $qualityId;
    }
}
