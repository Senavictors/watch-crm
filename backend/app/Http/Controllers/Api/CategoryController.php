<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category) => $this->toPayload($category));

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'hasQuality' => ['sometimes', 'boolean'],
        ]);

        $category = Category::create([
            'name' => $data['name'],
            'has_quality' => $data['hasQuality'] ?? false,
        ]);
        $this->audit('categories.created', 'Categoria criada.', $category);

        return response()->json($this->toPayload($category), 201);
    }

    public function update(Request $request, int $id)
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json(['message' => 'Categoria não encontrada.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
            'hasQuality' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('hasQuality', $data)) {
            $data['has_quality'] = $data['hasQuality'];
            unset($data['hasQuality']);
        }

        $category->fill($data);
        $category->save();
        $this->audit('categories.updated', 'Categoria atualizada.', $category);

        return response()->json($this->toPayload($category));
    }

    public function destroy(int $id)
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json(['message' => 'Categoria não encontrada.'], 404);
        }

        $category->delete();
        $this->audit('categories.deleted', 'Categoria removida.', null, ['category_id' => $id]);

        return response()->json(['ok' => true]);
    }

    private function toPayload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'hasQuality' => (bool) $category->has_quality,
        ];
    }
}
