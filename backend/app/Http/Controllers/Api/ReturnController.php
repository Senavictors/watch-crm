<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\User;
use App\Support\ReturnMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReturnController extends Controller
{
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

        return response()->json([
            'types' => ReturnMetadata::TYPES,
            'typeLabels' => ReturnMetadata::TYPE_LABELS,
            'statuses' => ReturnMetadata::STATUSES,
            'assignableUsers' => $assignableUsers,
        ]);
    }

    public function index(Request $request)
    {
        $query = ProductReturn::query()->with(['customer', 'assignedUser', 'items']);

        if ($request->filled('orderId')) {
            $query->where('order_id', $request->integer('orderId'));
        }

        if ($request->filled('orderItemId')) {
            $query->whereHas('items', fn ($q) => $q->where('order_item_id', $request->integer('orderItemId')));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->input('customer_id'));
        }

        $returns = $query
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ProductReturn $r) => $this->toPayload($r));

        return response()->json($returns);
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
                'status' => $data['status'] ?? 'Aguardando Recebimento',
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

            return $productReturn;
        });

        $productReturn->load(['customer', 'assignedUser', 'items']);

        $this->audit('returns.created', 'Garantia/Troca criada.', $productReturn, [
            'type' => $productReturn->type,
            'status' => $productReturn->status,
        ]);

        return response()->json($this->toPayload($productReturn), 201);
    }

    public function update(Request $request, int $id)
    {
        $productReturn = ProductReturn::query()->with(['customer', 'assignedUser', 'items'])->find($id);

        if (! $productReturn) {
            return response()->json(['message' => 'Garantia/Troca não encontrada.'], 404);
        }

        $data = $this->validateData($request, true);
        $previousStatus = $productReturn->status;

        $fieldMap = [
            'orderId'             => 'order_id',
            'customerId'          => 'customer_id',
            'assignedUserId'      => 'assigned_user_id',
            'type'                => 'type',
            'status'              => 'status',
            'reason'              => 'reason',
            'internalNotes'       => 'internal_notes',
            'resolutionNotes'     => 'resolution_notes',
            'receivedDate'        => 'received_date',
            'resolvedDate'        => 'resolved_date',
            'freightCostIn'       => 'freight_cost_in',
            'watchmakerCost'      => 'watchmaker_cost',
            'freightCostOut'      => 'freight_cost_out',
            'otherCosts'          => 'other_costs',
            'refundAmount'        => 'refund_amount',
            'returnTrackingCode'  => 'return_tracking_code',
            'shippedBackDate'     => 'shipped_back_date',
        ];

        foreach ($fieldMap as $input => $column) {
            if (array_key_exists($input, $data)) {
                $productReturn->{$column} = $data[$input];
            }
        }

        DB::transaction(function () use ($productReturn, $data) {
            $productReturn->save();

            if (array_key_exists('items', $data)) {
                $this->syncItems($productReturn, $data['items']);
            }
        });

        $productReturn->load(['customer', 'assignedUser', 'items']);

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

    public function destroy(int $id)
    {
        $productReturn = ProductReturn::find($id);

        if (! $productReturn) {
            return response()->json(['message' => 'Garantia/Troca não encontrada.'], 404);
        }

        $productReturn->delete();
        $this->audit('returns.deleted', 'Garantia/Troca removida.', null, ['return_id' => $id]);

        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $nullable = $partial ? 'sometimes' : 'nullable';

        return $request->validate([
            'orderId'            => [$nullable, 'integer', Rule::exists('orders', 'id')],
            'customerId'         => [$required, 'integer', Rule::exists('customers', 'id')],
            'assignedUserId'     => [$nullable, 'integer', Rule::exists('users', 'id')],
            'type'               => [$required, 'string', Rule::in(ReturnMetadata::TYPES)],
            'status'             => [$nullable, 'string', Rule::in(ReturnMetadata::STATUSES)],
            'reason'             => [$nullable, 'string'],
            'internalNotes'      => [$nullable, 'string'],
            'resolutionNotes'    => [$nullable, 'string'],
            'receivedDate'       => [$nullable, 'date'],
            'resolvedDate'       => [$nullable, 'date'],
            'freightCostIn'      => [$nullable, 'numeric', 'min:0'],
            'watchmakerCost'     => [$nullable, 'numeric', 'min:0'],
            'freightCostOut'     => [$nullable, 'numeric', 'min:0'],
            'otherCosts'         => [$nullable, 'numeric', 'min:0'],
            'refundAmount'       => [$nullable, 'numeric', 'min:0'],
            'returnTrackingCode' => [$nullable, 'string', 'max:255'],
            'shippedBackDate'    => [$nullable, 'date'],
            'items'              => [$required, 'array', 'min:1'],
            'items.*.orderItemId' => ['nullable', 'integer', Rule::exists('order_items', 'id')],
            'items.*.productId'   => ['nullable', 'integer', Rule::exists('products', 'id')],
            'items.*.productName' => ['required', 'string'],
            'items.*.productType' => ['required', 'string'],
            'items.*.brandName'   => ['nullable', 'string'],
            'items.*.modelName'   => ['nullable', 'string'],
            'items.*.qualityName' => ['nullable', 'string'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
            'items.*.unitPrice'   => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function syncItems(ProductReturn $productReturn, array $items): void
    {
        $productReturn->items()->delete();

        $payload = collect($items)
            ->map(fn (array $item) => [
                'return_id'     => $productReturn->id,
                'order_item_id' => $item['orderItemId'] ?? null,
                'product_id'    => $item['productId'] ?? null,
                'product_name'  => $item['productName'],
                'product_type'  => $item['productType'],
                'brand_name'    => $item['brandName'] ?? null,
                'model_name'    => $item['modelName'] ?? null,
                'quality_name'  => $item['qualityName'] ?? null,
                'quantity'      => (int) $item['quantity'],
                'unit_price'    => (float) $item['unitPrice'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ])
            ->all();

        $productReturn->items()->insert($payload);
    }

    private function toPayload(ProductReturn $r): array
    {
        $items = $r->relationLoaded('items') ? $r->items : $r->items()->get();

        return [
            'id'                  => $r->id,
            'orderId'             => $r->order_id,
            'customerId'          => $r->customer_id,
            'customerName'        => $r->customer?->name ?? '',
            'customerPhone'       => $r->customer?->phone ?? '',
            'createdByUserId'     => $r->created_by_user_id,
            'assignedUserId'      => $r->assigned_user_id,
            'assignedUserName'    => $r->assignedUser?->name ?? null,
            'type'                => $r->type,
            'typeLabel'           => ReturnMetadata::TYPE_LABELS[$r->type] ?? $r->type,
            'status'              => $r->status,
            'reason'              => $r->reason ?? '',
            'internalNotes'       => $r->internal_notes ?? '',
            'resolutionNotes'     => $r->resolution_notes ?? '',
            'receivedDate'        => $r->received_date ?? '',
            'resolvedDate'        => $r->resolved_date ?? '',
            'freightCostIn'       => (float) $r->freight_cost_in,
            'watchmakerCost'      => (float) $r->watchmaker_cost,
            'freightCostOut'      => (float) $r->freight_cost_out,
            'otherCosts'          => (float) $r->other_costs,
            'totalCost'           => $r->total_cost,
            'refundAmount'        => $r->refund_amount !== null ? (float) $r->refund_amount : null,
            'returnTrackingCode'  => $r->return_tracking_code ?? '',
            'shippedBackDate'     => $r->shipped_back_date ?? '',
            'items'               => $items->map(fn (ReturnItem $item) => $this->toItemPayload($item))->values(),
            'createdAt'           => $r->created_at?->toDateString() ?? '',
            'updatedAt'           => $r->updated_at?->toDateString() ?? '',
        ];
    }

    private function toItemPayload(ReturnItem $item): array
    {
        return [
            'id'          => $item->id,
            'orderItemId' => $item->order_item_id,
            'productId'   => $item->product_id,
            'productName' => $item->product_name,
            'productType' => $item->product_type,
            'brandName'   => $item->brand_name,
            'modelName'   => $item->model_name,
            'qualityName' => $item->quality_name,
            'quantity'    => $item->quantity,
            'unitPrice'   => (float) $item->unit_price,
        ];
    }
}
