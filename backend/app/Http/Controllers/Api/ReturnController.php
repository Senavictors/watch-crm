<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\ReturnStatusHistory;
use App\Models\User;
use App\Support\ApiPagination;
use App\Support\ReturnMetadata;
use App\Support\ReturnStatusTransition;
use App\Support\ReturnWarrantyWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReturnController extends Controller
{
    /**
     * @var list<string>
     */
    private const EAGER_LOADS = ['customer', 'assignedUser', 'items', 'order', 'statusHistories.actor'];

    private const LIST_EAGER_LOADS = ['customer', 'assignedUser', 'items', 'order'];

    public function metadata()
    {
        $assignableUsers = User::query()
            ->whereIn('role', ['garantia', 'gerente', 'admin'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->getRoleName(),
            ])
            ->values();

        $transitions = [];
        foreach (ReturnMetadata::STATUSES as $status) {
            $transitions[$status] = ReturnStatusTransition::validNextStatuses($status);
        }

        return response()->json([
            'types' => ReturnMetadata::TYPES,
            'typeLabels' => ReturnMetadata::TYPE_LABELS,
            'statuses' => ReturnMetadata::STATUSES,
            'transitions' => $transitions,
            'assignableUsers' => $assignableUsers,
        ]);
    }

    public function index(Request $request)
    {
        // TASK-026 (CA-02): o escopo é aplicado ANTES de qualquer filtro —
        // filtro só estreita o resultado, nunca amplia. Passar
        // `?assignedUserId=<outro>` ou `?customer_id=<outro>` continua
        // limitado ao que o usuário já podia ver.
        $query = ProductReturn::query()
            ->visibleTo($request->user())
            ->with(self::LIST_EAGER_LOADS);

        if ($request->filled('orderId')) {
            $query->where('order_id', $request->integer('orderId'));
        }

        if ($request->filled('orderItemId')) {
            $query->whereHas('items', fn ($q) => $q->where('order_item_id', $request->integer('orderItemId')));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->input('customer_id'));
        }

        if ($request->filled('assignedUserId')) {
            $query->where('assigned_user_id', $request->integer('assignedUserId'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($builder) use ($search) {
                $builder->where('id', ctype_digit($search) ? (int) $search : -1)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
            });
        }

        $returns = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(ApiPagination::perPage($request));

        return ApiPagination::response($returns, fn (ProductReturn $r) => $this->toPayload($r, false));
    }

    public function show(Request $request, int $id)
    {
        $productReturn = $this->findVisible($request, $id);
        if (! $productReturn instanceof ProductReturn) {
            return $productReturn;
        }

        return response()->json($this->toPayload($productReturn));
    }

    /**
     * TASK-026 (RN-04) — devolução fora do escopo responde 404, com a mesma
     * mensagem de um ID inexistente: 403 confirmaria a existência do
     * registro e permitiria estimar o volume de pós-venda da empresa
     * varrendo IDs.
     *
     * A checagem passa pela `ProductReturnPolicy` (CA-04) em vez de repetir
     * a condição aqui — a policy e o escopo `visibleTo()` são a mesma regra.
     *
     * @return ProductReturn|\Illuminate\Http\JsonResponse
     */
    private function findVisible(Request $request, int $id, string $ability = 'view')
    {
        $productReturn = ProductReturn::query()->with(self::EAGER_LOADS)->find($id);

        if (! $productReturn || $request->user()->cannot($ability, $productReturn)) {
            return response()->json(['message' => 'Garantia/Troca não encontrada.'], 404);
        }

        return $productReturn;
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $productReturn = DB::transaction(function () use ($data, $request) {
            $productReturn = ProductReturn::create([
                'order_id' => $data['orderId'] ?? null,
                'customer_id' => $data['customerId'],
                'created_by_user_id' => $request->user()->id,
                'assigned_user_id' => $data['assignedUserId'] ?? null,
                'type' => $data['type'],
                // RN-03/CA-01: nasce sempre em "Aguardando Recebimento",
                // ignorando qualquer `status` enviado no body de criação —
                // não faz sentido uma devolução nascer em outra etapa do
                // fluxo.
                'status' => 'Aguardando Recebimento',
                'reason' => $data['reason'] ?? null,
                'internal_notes' => $data['internalNotes'] ?? null,
                'resolution_notes' => $data['resolutionNotes'] ?? null,
                'received_date' => $data['receivedDate'] ?? null,
                'resolved_date' => $data['resolvedDate'] ?? null,
                'freight_cost_in' => $data['freightCostIn'] ?? 0,
                'watchmaker_cost' => $data['watchmakerCost'] ?? 0,
                'freight_cost_out' => $data['freightCostOut'] ?? 0,
                'other_costs' => $data['otherCosts'] ?? 0,
                'refund_amount' => $data['refundAmount'] ?? null,
                'return_tracking_code' => $data['returnTrackingCode'] ?? null,
                'shipped_back_date' => $data['shippedBackDate'] ?? null,
            ]);

            $this->syncItems($productReturn, $data['items']);

            ReturnStatusHistory::create([
                'return_id' => $productReturn->id,
                'from_status' => null,
                'to_status' => $productReturn->status,
                'actor_user_id' => $request->user()->id,
                'created_at' => now(),
            ]);

            return $productReturn;
        });

        $productReturn->load(self::EAGER_LOADS);

        $this->audit('returns.created', 'Garantia/Troca criada.', $productReturn, [
            'type' => $productReturn->type,
            'status' => $productReturn->status,
        ]);

        return response()->json($this->toPayload($productReturn), 201);
    }

    public function update(Request $request, int $id)
    {
        $productReturn = $this->findVisible($request, $id, 'update');
        if (! $productReturn instanceof ProductReturn) {
            return $productReturn;
        }

        // TASK-025: registro estornado é histórico fechado — não volta a
        // andar na máquina de estados nem tem valores reeditados.
        if ($productReturn->isVoided()) {
            return response()->json([
                'message' => 'Esta garantia/troca está estornada e não pode mais ser alterada.',
                'code' => 'return_voided',
            ], 422);
        }

        $data = $this->validateData($request, true);
        $previousStatus = $productReturn->status;
        $newStatus = $data['status'] ?? $previousStatus;

        if ($newStatus !== $previousStatus && ! ReturnStatusTransition::isValid($previousStatus, $newStatus)) {
            return response()->json([
                'message' => "Transição de '{$previousStatus}' para '{$newStatus}' não é permitida.",
            ], 422);
        }

        $fieldMap = [
            'orderId' => 'order_id',
            'customerId' => 'customer_id',
            'assignedUserId' => 'assigned_user_id',
            'type' => 'type',
            'status' => 'status',
            'reason' => 'reason',
            'internalNotes' => 'internal_notes',
            'resolutionNotes' => 'resolution_notes',
            'receivedDate' => 'received_date',
            'resolvedDate' => 'resolved_date',
            'freightCostIn' => 'freight_cost_in',
            'watchmakerCost' => 'watchmaker_cost',
            'freightCostOut' => 'freight_cost_out',
            'otherCosts' => 'other_costs',
            'refundAmount' => 'refund_amount',
            'returnTrackingCode' => 'return_tracking_code',
            'shippedBackDate' => 'shipped_back_date',
        ];

        foreach ($fieldMap as $input => $column) {
            if (array_key_exists($input, $data)) {
                $productReturn->{$column} = $data[$input];
            }
        }

        DB::transaction(function () use ($productReturn, $data, $previousStatus, $newStatus, $request) {
            $productReturn->save();

            if (array_key_exists('items', $data)) {
                $this->syncItems($productReturn, $data['items']);
            }

            if ($newStatus !== $previousStatus) {
                ReturnStatusHistory::create([
                    'return_id' => $productReturn->id,
                    'from_status' => $previousStatus,
                    'to_status' => $newStatus,
                    'actor_user_id' => $request->user()->id,
                    'created_at' => now(),
                ]);
            }
        });

        $productReturn->load(self::EAGER_LOADS);

        $metadata = [
            'type' => $productReturn->type,
            'status' => $productReturn->status,
        ];
        if ($previousStatus !== $productReturn->status) {
            $metadata['previous_status'] = $previousStatus;
        }

        $this->audit('returns.updated', 'Garantia/Troca atualizada.', $productReturn, $metadata);

        return response()->json($this->toPayload($productReturn));
    }

    /**
     * TASK-025 (RN-04 / ADR-007): devolução com efeito financeiro ou com
     * histórico de status não é apagada — é estornada (`void`). Só um
     * registro recém-criado e sem impacto pode ser excluído de fato.
     */
    public function destroy(Request $request, int $id)
    {
        $productReturn = $this->findVisible($request, $id, 'delete');
        if (! $productReturn instanceof ProductReturn) {
            return $productReturn;
        }

        $footprint = $productReturn->financialFootprint();

        if ($footprint !== []) {
            return response()->json([
                'message' => 'Esta garantia/troca não pode ser excluída porque já tem '.
                    collect($footprint)->keys()->join(', ', ' e ').
                    '. Use o estorno para anular o efeito preservando o histórico.',
                'code' => 'return_has_financial_history',
                'footprint' => $footprint,
            ], 409);
        }

        DB::transaction(function () use ($productReturn, $id) {
            $productReturn->delete();
            $this->audit('returns.deleted', 'Garantia/Troca removida.', null, ['return_id' => $id]);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Estorno: mantém o fato, o autor e o motivo, e tira o registro de
     * faturamento, comissões, metas e dashboard (ADR-007).
     */
    public function void(Request $request, int $id)
    {
        $productReturn = $this->findVisible($request, $id, 'delete');
        if (! $productReturn instanceof ProductReturn) {
            return $productReturn;
        }

        if ($productReturn->isVoided()) {
            return response()->json(['message' => 'Esta garantia/troca já está estornada.'], 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($productReturn, $request, $data) {
            $productReturn->voided_at = now();
            $productReturn->voided_by_user_id = $request->user()->id;
            $productReturn->void_reason = $data['reason'];
            $productReturn->save();

            $this->audit('returns.voided', 'Garantia/Troca estornada.', $productReturn, [
                'reason' => $data['reason'],
                'status' => $productReturn->status,
                'refund_amount' => $productReturn->refund_amount !== null ? (float) $productReturn->refund_amount : null,
            ]);
        });

        $productReturn->load(self::EAGER_LOADS);

        return response()->json($this->toPayload($productReturn));
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $nullable = $partial ? 'sometimes' : 'nullable';

        return $request->validate([
            'orderId' => [$nullable, 'integer', Rule::exists('orders', 'id')],
            'customerId' => [$required, 'integer', Rule::exists('customers', 'id')],
            'assignedUserId' => [$nullable, 'integer', Rule::exists('users', 'id')],
            'type' => [$required, 'string', Rule::in(ReturnMetadata::TYPES)],
            'status' => [$nullable, 'string', Rule::in(ReturnMetadata::STATUSES)],
            'reason' => [$nullable, 'string'],
            'internalNotes' => [$nullable, 'string'],
            'resolutionNotes' => [$nullable, 'string'],
            'receivedDate' => [$nullable, 'date'],
            'resolvedDate' => [$nullable, 'date'],
            'freightCostIn' => [$nullable, 'numeric', 'min:0'],
            'watchmakerCost' => [$nullable, 'numeric', 'min:0'],
            'freightCostOut' => [$nullable, 'numeric', 'min:0'],
            'otherCosts' => [$nullable, 'numeric', 'min:0'],
            'refundAmount' => [$nullable, 'numeric', 'min:0'],
            'returnTrackingCode' => [$nullable, 'string', 'max:255'],
            'shippedBackDate' => [$nullable, 'date'],
            'items' => [$required, 'array', 'min:1'],
            'items.*.orderItemId' => ['nullable', 'integer', Rule::exists('order_items', 'id')],
            'items.*.productId' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'items.*.productName' => ['required', 'string'],
            'items.*.productType' => ['required', 'string'],
            'items.*.brandName' => ['nullable', 'string'],
            'items.*.modelName' => ['nullable', 'string'],
            'items.*.qualityName' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unitPrice' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function syncItems(ProductReturn $productReturn, array $items): void
    {
        $productReturn->items()->delete();

        $payload = collect($items)
            ->map(fn (array $item) => [
                'return_id' => $productReturn->id,
                'order_item_id' => $item['orderItemId'] ?? null,
                'product_id' => $item['productId'] ?? null,
                'product_name' => $item['productName'],
                'product_type' => $item['productType'],
                'brand_name' => $item['brandName'] ?? null,
                'model_name' => $item['modelName'] ?? null,
                'quality_name' => $item['qualityName'] ?? null,
                'quantity' => (int) $item['quantity'],
                'unit_price' => (float) $item['unitPrice'],
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        $productReturn->items()->insert($payload);
    }

    private function toPayload(ProductReturn $r, bool $includeHistory = true): array
    {
        $items = $r->relationLoaded('items') ? $r->items : $r->items()->get();

        return [
            'id' => $r->id,
            'orderId' => $r->order_id,
            'customerId' => $r->customer_id,
            'customerName' => $r->customer?->name ?? '',
            'customerPhone' => $r->customer?->phone ?? '',
            'createdByUserId' => $r->created_by_user_id,
            'assignedUserId' => $r->assigned_user_id,
            'assignedUserName' => $r->assignedUser?->name ?? null,
            'type' => $r->type,
            'typeLabel' => ReturnMetadata::TYPE_LABELS[$r->type] ?? $r->type,
            'status' => $r->status,
            'reason' => $r->reason ?? '',
            'internalNotes' => $r->internal_notes ?? '',
            'resolutionNotes' => $r->resolution_notes ?? '',
            'receivedDate' => $r->received_date ?? '',
            'resolvedDate' => $r->resolved_date ?? '',
            'freightCostIn' => (float) $r->freight_cost_in,
            'watchmakerCost' => (float) $r->watchmaker_cost,
            'freightCostOut' => (float) $r->freight_cost_out,
            'otherCosts' => (float) $r->other_costs,
            'totalCost' => $r->total_cost,
            'refundAmount' => $r->refund_amount !== null ? (float) $r->refund_amount : null,
            'returnTrackingCode' => $r->return_tracking_code ?? '',
            'shippedBackDate' => $r->shipped_back_date ?? '',
            'items' => $items->map(fn (ReturnItem $item) => $this->toItemPayload($item))->values(),
            'statusHistory' => $includeHistory ? $this->toStatusHistoryPayload($r) : [],
            'withinWarrantyWindow' => $this->withinWarrantyWindow($r),
            // TASK-025: registro estornado continua visível e auditável, mas
            // fora de todo agregado financeiro.
            'voidedAt' => $r->voided_at?->toIso8601String(),
            'voidedByUserId' => $r->voided_by_user_id,
            'voidReason' => $r->void_reason,
            'createdAt' => $r->created_at?->toDateString() ?? '',
            'updatedAt' => $r->updated_at?->toDateString() ?? '',
        ];
    }

    private function toItemPayload(ReturnItem $item): array
    {
        return [
            'id' => $item->id,
            'orderItemId' => $item->order_item_id,
            'productId' => $item->product_id,
            'productName' => $item->product_name,
            'productType' => $item->product_type,
            'brandName' => $item->brand_name,
            'modelName' => $item->model_name,
            'qualityName' => $item->quality_name,
            'quantity' => $item->quantity,
            'unitPrice' => (float) $item->unit_price,
        ];
    }

    /**
     * Ordenado por `created_at` asc — a própria relação `ProductReturn::
     * statusHistories()` já ordena assim, mas re-afirmamos a ordenação aqui
     * caso a coleção chegue de outro caminho (ex.: relação carregada sem o
     * `orderBy` da definição, hipótese que não ocorre hoje, mas evita
     * acoplar o contrato do payload à implementação da relação).
     */
    private function toStatusHistoryPayload(ProductReturn $r): array
    {
        $histories = $r->relationLoaded('statusHistories')
            ? $r->statusHistories
            : $r->statusHistories()->with('actor')->get();

        return $histories
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->map(fn (ReturnStatusHistory $h) => [
                'fromStatus' => $h->from_status,
                'toStatus' => $h->to_status,
                'actorUserId' => $h->actor_user_id,
                'actorUserName' => $h->actor?->name ?? null,
                'notes' => $h->notes,
                'createdAt' => $h->created_at?->toIso8601String() ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * RN-01: `null` quando a devolução não tem `order_id` vinculado ou o
     * pedido vinculado não tem `sale_date`; cálculo em si isolado em
     * `Support\ReturnWarrantyWindow`.
     */
    private function withinWarrantyWindow(ProductReturn $r): ?bool
    {
        $order = $r->relationLoaded('order') ? $r->order : $r->order()->first();

        if ($order === null || $order->sale_date === null) {
            return null;
        }

        return ReturnWarrantyWindow::isWithinWindow(Carbon::parse($order->sale_date));
    }
}
