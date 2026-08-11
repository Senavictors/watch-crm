<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\WatchModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // TASK-025: FK era CASCADE em `models` — e modelo cascateava em
        // `products`. Apagar uma categoria varria parte do catálogo.
        $conflict = $this->conflictIfInUse([
            'modelos' => WatchModel::query()->where('category_id', $category->id)->count(),
        ], 'Esta categoria', 'category_in_use');

        if ($conflict) {
            return $conflict;
        }

        DB::transaction(function () use ($category, $id) {
            $category->delete();
            $this->audit('categories.deleted', 'Categoria removida.', null, ['category_id' => $id]);
        });

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
