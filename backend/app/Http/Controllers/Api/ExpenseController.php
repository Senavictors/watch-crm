<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Support\ApiPagination;
use App\Support\ExpenseMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * TASK-006 — módulo de despesas gerais. CRUD simples, sem ownership (RN-02
 * do ADR-003: despesa é dado financeiro restrito a owner/admin, não algo
 * "do vendedor que criou") — mesmo padrão de `BrandController`/
 * `CategoryController` (permissão na rota, sem Policy).
 */
class ExpenseController extends Controller
{
    public function metadata()
    {
        return response()->json([
            'categories' => ExpenseMetadata::CATEGORIES,
        ]);
    }

    public function index(Request $request)
    {
        $query = Expense::query()->with(['creator', 'voidedBy']);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('startDate') && $request->filled('endDate')) {
            // RN-03: filtro por período usa a data da despesa.
            $query->whereBetween('expense_date', [$request->input('startDate'), $request->input('endDate')]);
        }

        // TASK-025: estornadas continuam listadas (marcadas), mas o resumo
        // só soma o que de fato pesa no resultado.
        $summary = [
            'totalAmount' => (float) (clone $query)->effective()->sum('amount'),
            'totalCount' => (clone $query)->effective()->count(),
            'voidedCount' => (clone $query)->whereNotNull('voided_at')->count(),
        ];

        $expenses = $query
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(ApiPagination::perPage($request));

        return ApiPagination::response($expenses, fn (Expense $expense) => $this->toPayload($expense), ['summary' => $summary]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $expense = Expense::create([
            ...$data,
            'created_by_user_id' => $request->user()->id,
        ]);
        $expense->load('creator');

        $this->audit('expenses.created', 'Despesa registrada.', $expense, [
            'category' => $expense->category,
            'amount' => (float) $expense->amount,
        ]);

        return response()->json($this->toPayload($expense), 201);
    }

    public function update(Request $request, int $id)
    {
        $expense = Expense::find($id);

        if (! $expense) {
            return response()->json(['message' => 'Despesa não encontrada.'], 404);
        }

        $data = $this->validatedData($request, true);

        $expense->fill($data);
        $expense->save();
        $expense->load('creator');

        $this->audit('expenses.updated', 'Despesa atualizada.', $expense, [
            'category' => $expense->category,
            'amount' => (float) $expense->amount,
        ]);

        return response()->json($this->toPayload($expense));
    }

    /**
     * TASK-025 (RN-03 / ADR-007): toda despesa lançada já tem efeito no
     * resultado, então nenhuma é apagada. Corrigir um lançamento errado é
     * estorná-lo e lançar de novo — o fato e o motivo ficam registrados.
     */
    public function destroy(int $id)
    {
        $expense = Expense::find($id);

        if (! $expense) {
            return response()->json(['message' => 'Despesa não encontrada.'], 404);
        }

        return response()->json([
            'message' => 'Despesas não são excluídas: elas já entraram no resultado financeiro. Use o estorno para anular o lançamento preservando o histórico.',
            'code' => 'expense_requires_void',
        ], 409);
    }

    public function void(Request $request, int $id)
    {
        $expense = Expense::find($id);

        if (! $expense) {
            return response()->json(['message' => 'Despesa não encontrada.'], 404);
        }

        if ($expense->isVoided()) {
            return response()->json(['message' => 'Esta despesa já está estornada.'], 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($expense, $request, $data) {
            $expense->voided_at = now();
            $expense->voided_by_user_id = $request->user()->id;
            $expense->void_reason = $data['reason'];
            $expense->save();

            $this->audit('expenses.voided', 'Despesa estornada.', $expense, [
                'category' => $expense->category,
                'amount' => (float) $expense->amount,
                'reason' => $data['reason'],
            ]);
        });

        $expense->load(['creator', 'voidedBy']);

        return response()->json($this->toPayload($expense));
    }

    private function validatedData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'category' => [$required, 'string', Rule::in(ExpenseMetadata::CATEGORIES)],
            'description' => [$required, 'string', 'max:255'],
            'amount' => [$required, 'numeric', 'min:0.01'],
            'expenseDate' => [$required, 'date'],
        ]);

        return $this->toDatabaseKeys($data);
    }

    private function toDatabaseKeys(array $data): array
    {
        if (array_key_exists('expenseDate', $data)) {
            $data['expense_date'] = $data['expenseDate'];
            unset($data['expenseDate']);
        }

        return $data;
    }

    private function toPayload(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'category' => $expense->category,
            'description' => $expense->description,
            'amount' => (float) $expense->amount,
            'expenseDate' => $expense->expense_date->toDateString(),
            'createdByUserId' => $expense->created_by_user_id,
            'createdByUserName' => $expense->creator?->name,
            'createdAt' => $expense->created_at?->toIso8601String(),
            'voidedAt' => $expense->voided_at?->toIso8601String(),
            'voidedByUserId' => $expense->voided_by_user_id,
            'voidedByUserName' => $expense->voidedBy?->name,
            'voidReason' => $expense->void_reason,
        ];
    }
}
