<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Support\ExpenseMetadata;
use Illuminate\Http\Request;
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
        $query = Expense::query()->with('creator');

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('startDate') && $request->filled('endDate')) {
            // RN-03: filtro por período usa a data da despesa.
            $query->whereBetween('expense_date', [$request->input('startDate'), $request->input('endDate')]);
        }

        $expenses = $query
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Expense $expense) => $this->toPayload($expense));

        return response()->json($expenses);
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

    public function destroy(int $id)
    {
        $expense = Expense::find($id);

        if (! $expense) {
            return response()->json(['message' => 'Despesa não encontrada.'], 404);
        }

        $expense->delete();
        $this->audit('expenses.deleted', 'Despesa removida.', null, [
            'expense_id' => $id,
            'category' => $expense->category,
            'amount' => (float) $expense->amount,
        ]);

        return response()->json(['ok' => true]);
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
        ];
    }
}
