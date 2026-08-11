<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quality;
use App\Models\WatchModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $this->audit('qualities.created', 'Qualidade criada.', $quality);

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
        $this->audit('qualities.updated', 'Qualidade atualizada.', $quality);

        return response()->json($this->toPayload($quality));
    }

    public function destroy(int $id)
    {
        $quality = Quality::find($id);

        if (! $quality) {
            return response()->json(['message' => 'Qualidade não encontrada.'], 404);
        }

        // TASK-025: FK era CASCADE em `models` (que cascateava em produtos).
        $conflict = $this->conflictIfInUse([
            'modelos' => WatchModel::query()->where('quality_id', $quality->id)->count(),
        ], 'Esta qualidade', 'quality_in_use');

        if ($conflict) {
            return $conflict;
        }

        DB::transaction(function () use ($quality, $id) {
            $quality->delete();
            $this->audit('qualities.deleted', 'Qualidade removida.', null, ['quality_id' => $id]);
        });

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
