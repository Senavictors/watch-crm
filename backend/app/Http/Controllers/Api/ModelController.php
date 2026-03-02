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
            ->with('quality')
            ->orderBy('id')
            ->get()
            ->map(fn (WatchModel $model) => $this->toPayload($model));

        return response()->json($models);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('models', 'name')
                    ->where('brand_id', $request->input('brandId'))
                    ->where('quality_id', $request->input('qualityId')),
            ],
            'brandId' => ['required', 'integer', 'exists:brands,id'],
            'qualityId' => ['required', 'integer', 'exists:qualities,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('models', 'public');
        }

        $model = WatchModel::create([
            'name' => $data['name'],
            'brand_id' => $data['brandId'],
            'quality_id' => $data['qualityId'],
            'image_path' => $imagePath,
        ]);
        $model->load('quality');

        return response()->json($this->toPayload($model), 201);
    }

    public function update(Request $request, int $id)
    {
        $model = WatchModel::find($id);

        if (! $model) {
            return response()->json(['message' => 'Modelo não encontrado.'], 404);
        }

        $brandId = $request->input('brandId', $model->brand_id);
        $qualityId = $request->input('qualityId', $model->quality_id);

        $data = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('models', 'name')
                    ->where('brand_id', $brandId)
                    ->where('quality_id', $qualityId)
                    ->ignore($model->id),
            ],
            'brandId' => ['sometimes', 'integer', 'exists:brands,id'],
            'qualityId' => ['sometimes', 'integer', 'exists:qualities,id'],
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

        $model->fill($data);
        $model->save();
        $model->load('quality');

        return response()->json($this->toPayload($model));
    }

    public function destroy(int $id)
    {
        $model = WatchModel::find($id);

        if (! $model) {
            return response()->json(['message' => 'Modelo não encontrado.'], 404);
        }

        $model->delete();

        return response()->json(['ok' => true]);
    }

    private function toPayload(WatchModel $model): array
    {
        return [
            'id' => $model->id,
            'brandId' => $model->brand_id,
            'name' => $model->name,
            'qualityId' => $model->quality_id,
            'qualityName' => $model->quality?->name,
            'imageUrl' => $model->image_path ? url(Storage::url($model->image_path)) : null,
        ];
    }
}
