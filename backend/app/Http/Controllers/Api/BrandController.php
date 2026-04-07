<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Brand $brand) => $this->toPayload($brand));

        return response()->json($brands);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:brands,name'],
        ]);

        $brand = Brand::create($data);
        $this->audit('brands.created', 'Marca criada.', $brand);

        return response()->json($this->toPayload($brand), 201);
    }

    public function update(Request $request, int $id)
    {
        $brand = Brand::find($id);

        if (! $brand) {
            return response()->json(['message' => 'Marca não encontrada.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($brand->id)],
        ]);

        $brand->fill($data);
        $brand->save();
        $this->audit('brands.updated', 'Marca atualizada.', $brand);

        return response()->json($this->toPayload($brand));
    }

    public function destroy(int $id)
    {
        $brand = Brand::find($id);

        if (! $brand) {
            return response()->json(['message' => 'Marca não encontrada.'], 404);
        }

        $brand->delete();
        $this->audit('brands.deleted', 'Marca removida.', null, ['brand_id' => $id]);

        return response()->json(['ok' => true]);
    }

    private function toPayload(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
        ];
    }
}
