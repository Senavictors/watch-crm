<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Goal;
use App\Models\User;
use App\Models\WatchModel;
use App\Support\GoalProgressCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GoalController extends Controller
{
    public function metadata()
    {
        $sellers = User::query()
            ->where('role', 'vendedor')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->values();

        $brands = Brand::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name])
            ->values();

        $models = WatchModel::query()
            ->with('brand')
            ->orderBy('name')
            ->get()
            ->map(fn (WatchModel $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'brandId' => $m->brand_id,
                'productType' => $m->product_type,
            ])
            ->values();

        return response()->json([
            'sellers' => $sellers,
            'brands' => $brands,
            'models' => $models,
            'scopes' => [
                ['value' => 'company', 'label' => 'Empresa'],
                ['value' => 'user', 'label' => 'Vendedor'],
            ],
            'calculationTypes' => [
                ['value' => 'total_value', 'label' => 'Valor Total de Vendas'],
                ['value' => 'quantity', 'label' => 'Quantidade de Itens'],
            ],
            'productTypeFilters' => [
                ['value' => '', 'label' => 'Todos'],
                ['value' => 'WATCH', 'label' => 'Relógios'],
                ['value' => 'BOX', 'label' => 'Caixas'],
            ],
            'periodCycles' => [
                ['value' => 'monthly', 'label' => 'Mensal'],
                ['value' => 'quarterly', 'label' => 'Trimestral'],
                ['value' => 'semiannual', 'label' => 'Semestral'],
                ['value' => 'annual', 'label' => 'Anual'],
            ],
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Goal::query()->with(['creator', 'targetUser', 'brand', 'watchModel', 'intervals']);

        if (! $user->canAccessAllRecords()) {
            $query->where(function ($q) use ($user) {
                $q->where('scope', 'company')
                    ->orWhere('target_user_id', $user->id);
            });
        }

        $goals = $query->orderByDesc('created_at')->get()->map(fn (Goal $g) => $this->toPayload($g));

        return response()->json($goals);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $goal = DB::transaction(function () use ($data, $request) {
            $goal = Goal::create([
                'created_by_user_id' => $request->user()->id,
                'target_user_id' => $data['scope'] === 'user' ? $data['targetUserId'] : null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'scope' => $data['scope'],
                'calculation_type' => $data['calculationType'],
                'product_type_filter' => $data['productTypeFilter'] ?: null,
                'brand_id' => $data['brandId'] ?? null,
                'model_id' => $data['modelId'] ?? null,
                'period_cycle' => $data['periodCycle'],
                'start_date' => $data['startDate'],
                'end_date' => $data['endDate'],
                'status' => 'active',
            ]);

            $this->syncIntervals($goal, $data['intervals']);

            return $goal;
        });

        $goal->load(['creator', 'targetUser', 'brand', 'watchModel', 'intervals']);

        $this->audit('goals.created', 'Meta criada.', $goal);

        return response()->json($this->toPayload($goal), 201);
    }

    public function update(Request $request, int $id)
    {
        $goal = Goal::query()->with(['creator', 'targetUser', 'brand', 'watchModel', 'intervals'])->find($id);

        if (! $goal) {
            return response()->json(['message' => 'Meta não encontrada.'], 404);
        }

        $this->authorize('update', $goal);

        $data = $this->validateData($request, true);

        DB::transaction(function () use ($goal, $data) {
            $fieldMap = [
                'name' => 'name',
                'description' => 'description',
                'scope' => 'scope',
                'calculationType' => 'calculation_type',
                'productTypeFilter' => 'product_type_filter',
                'brandId' => 'brand_id',
                'modelId' => 'model_id',
                'periodCycle' => 'period_cycle',
                'startDate' => 'start_date',
                'endDate' => 'end_date',
                'status' => 'status',
            ];

            foreach ($fieldMap as $input => $column) {
                if (array_key_exists($input, $data)) {
                    $value = $data[$input];
                    if ($input === 'productTypeFilter') {
                        $value = $value ?: null;
                    }
                    $goal->{$column} = $value;
                }
            }

            if (array_key_exists('targetUserId', $data)) {
                $goal->target_user_id = ($goal->scope === 'user') ? $data['targetUserId'] : null;
            }

            $goal->save();

            if (array_key_exists('intervals', $data)) {
                $this->syncIntervals($goal, $data['intervals']);
            }
        });

        $goal->load(['creator', 'targetUser', 'brand', 'watchModel', 'intervals']);

        $this->audit('goals.updated', 'Meta atualizada.', $goal);

        return response()->json($this->toPayload($goal));
    }

    public function destroy(int $id)
    {
        $goal = Goal::find($id);

        if (! $goal) {
            return response()->json(['message' => 'Meta não encontrada.'], 404);
        }

        $this->authorize('delete', $goal);

        $goal->delete();
        $this->audit('goals.deleted', 'Meta removida.', null, ['goal_id' => $id]);

        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'description' => [$partial ? 'sometimes' : 'nullable', 'string'],
            'scope' => [$required, 'string', Rule::in(['company', 'user'])],
            'targetUserId' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'calculationType' => [$required, 'string', Rule::in(['total_value', 'quantity'])],
            'productTypeFilter' => [$partial ? 'sometimes' : 'nullable', 'string', Rule::in(['', 'WATCH', 'BOX'])],
            'brandId' => [$partial ? 'sometimes' : 'nullable', 'integer', Rule::exists('brands', 'id')],
            'modelId' => [$partial ? 'sometimes' : 'nullable', 'integer', Rule::exists('models', 'id')],
            'periodCycle' => [$required, 'string', Rule::in(['monthly', 'quarterly', 'semiannual', 'annual'])],
            'startDate' => [$required, 'date'],
            'endDate' => [$required, 'date', 'after_or_equal:startDate'],
            'status' => [$partial ? 'sometimes' : 'nullable', 'string', Rule::in(['active', 'archived'])],
            'intervals' => [$required, 'array', 'min:1'],
            'intervals.*.startDate' => ['required', 'date'],
            'intervals.*.endDate' => ['required', 'date'],
            'intervals.*.targetValue' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function syncIntervals(Goal $goal, array $intervals): void
    {
        $goal->intervals()->delete();

        $payload = collect($intervals)->map(fn (array $interval) => [
            'goal_id' => $goal->id,
            'start_date' => $interval['startDate'],
            'end_date' => $interval['endDate'],
            'target_value' => (float) $interval['targetValue'],
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        $goal->intervals()->insert($payload);
    }

    private function toPayload(Goal $goal): array
    {
        $intervals = GoalProgressCalculator::calculate($goal);

        $totalTarget = $intervals->sum('targetValue');
        $totalCurrent = $intervals->sum('currentValue');

        return [
            'id' => $goal->id,
            'createdByUserId' => $goal->created_by_user_id,
            'createdByUserName' => $goal->creator?->name,
            'targetUserId' => $goal->target_user_id,
            'targetUserName' => $goal->targetUser?->name,
            'name' => $goal->name,
            'description' => $goal->description,
            'scope' => $goal->scope,
            'calculationType' => $goal->calculation_type,
            'productTypeFilter' => $goal->product_type_filter,
            'brandId' => $goal->brand_id,
            'brandName' => $goal->brand?->name,
            'modelId' => $goal->model_id,
            'modelName' => $goal->watchModel?->name,
            'periodCycle' => $goal->period_cycle,
            'startDate' => $goal->start_date->format('Y-m-d'),
            'endDate' => $goal->end_date->format('Y-m-d'),
            'status' => $goal->status,
            'totalTarget' => (float) $totalTarget,
            'totalCurrent' => (float) $totalCurrent,
            'totalPercentage' => $totalTarget > 0 ? round(($totalCurrent / $totalTarget) * 100, 1) : 0,
            'intervals' => $intervals->values()->all(),
            'createdAt' => $goal->created_at->toIso8601String(),
        ];
    }
}
