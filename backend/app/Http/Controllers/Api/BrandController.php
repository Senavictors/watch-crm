<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // TASK-025: antes desta task a FK era CASCADE — apagar a marca
        // levava junto modelos e produtos.
        $conflict = $this->conflictIfInUse([
            'modelos' => $brand->models()->count(),
            'produtos' => Product::query()->where('brand_id', $brand->id)->count(),
        ], 'Esta marca', 'brand_in_use');

        if ($conflict) {
            return $conflict;
        }

        DB::transaction(function () use ($brand, $id) {
            $brand->delete();
            $this->audit('brands.deleted', 'Marca removida.', null, ['brand_id' => $id]);
        });

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
