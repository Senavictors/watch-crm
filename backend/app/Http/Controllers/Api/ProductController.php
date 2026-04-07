<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->with(['brand', 'watchModel.quality'])
            ->orderBy('id')
            ->get()
            ->map(fn (Product $product) => $this->toPayload($product));

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'brandId' => ['required', 'integer', 'exists:brands,id'],
            'modelId' => [
                'required',
                'integer',
                Rule::exists('models', 'id')->where('brand_id', $request->input('brandId')),
            ],
            'cost' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'in:IN_STOCK,SUPPLIER'],
            'qty' => ['required', 'integer', 'min:0'],
        ]);

        $product = Product::create([
            'brand_id' => $data['brandId'],
            'model_id' => $data['modelId'],
            'cost' => $data['cost'],
            'price' => $data['price'],
            'stock' => $data['stock'],
            'qty' => $data['qty'],
        ]);
        $product->load(['brand', 'watchModel.quality']);
        $this->audit('products.created', 'Produto criado.', $product);

        return response()->json($this->toPayload($product), 201);
    }

    public function update(Request $request, int $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        if ($request->has('brandId') && ! $request->has('modelId')) {
            return response()->json(['message' => 'modelId é obrigatório quando brandId é informado.'], 422);
        }

        $brandId = $request->input('brandId', $product->brand_id);

        $data = $request->validate([
            'brandId' => ['sometimes', 'integer', 'exists:brands,id'],
            'modelId' => [
                'sometimes',
                'integer',
                Rule::exists('models', 'id')->where('brand_id', $brandId),
            ],
            'cost' => ['sometimes', 'numeric', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'in:IN_STOCK,SUPPLIER'],
            'qty' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (array_key_exists('brandId', $data)) {
            $data['brand_id'] = $data['brandId'];
            unset($data['brandId']);
        }

        if (array_key_exists('modelId', $data)) {
            $data['model_id'] = $data['modelId'];
            unset($data['modelId']);
        }

        $product->fill($data);
        $product->save();
        $product->load(['brand', 'watchModel.quality']);
        $this->audit('products.updated', 'Produto atualizado.', $product);

        return response()->json($this->toPayload($product));
    }

    public function destroy(int $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        $product->delete();
        $this->audit('products.deleted', 'Produto removido.', null, ['product_id' => $id]);

        return response()->json(['ok' => true]);
    }

    private function toPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'brandId' => $product->brand_id,
            'modelId' => $product->model_id,
            'brand' => $product->brand?->name,
            'model' => $product->watchModel?->name,
            'modelQualityName' => $product->watchModel?->quality?->name,
            'cost' => (float) $product->cost,
            'price' => (float) $product->price,
            'stock' => $product->stock,
            'qty' => $product->qty,
        ];
    }
}
