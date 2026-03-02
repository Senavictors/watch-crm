<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quality;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QualityController extends Controller
{
    public function index()
    {
        $qualities = Quality::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Quality $quality) => $this->toPayload($quality));

        return response()->json($qualities);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:qualities,name'],
        ]);

        $quality = Quality::create($data);

        return response()->json($this->toPayload($quality), 201);
    }

    public function update(Request $request, int $id)
    {
        $quality = Quality::find($id);

        if (! $quality) {
            return response()->json(['message' => 'Qualidade não encontrada.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('qualities', 'name')->ignore($quality->id)],
        ]);

        $quality->fill($data);
        $quality->save();

        return response()->json($this->toPayload($quality));
    }

    public function destroy(int $id)
    {
        $quality = Quality::find($id);

        if (! $quality) {
            return response()->json(['message' => 'Qualidade não encontrada.'], 404);
        }

        $quality->delete();

        return response()->json(['ok' => true]);
    }

    private function toPayload(Quality $quality): array
    {
        return [
            'id' => $quality->id,
            'name' => $quality->name,
        ];
    }
}
