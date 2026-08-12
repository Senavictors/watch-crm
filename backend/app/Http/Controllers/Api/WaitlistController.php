<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\ApiPagination;
use App\Support\LockedTransaction;
use App\Support\WaitlistMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * TASK-018 — lista de espera por produto (RN-01: não cria oportunidade
 * financeira; RN-02: respeita visibilidade/ownership do vendedor; RN-03:
 * sinaliza disponibilidade, mas não dispara nada automático).
 */
class WaitlistController extends Controller
{
    /**
     * @var list<string>
     */
    private const EAGER_LOADS = ['customer', 'sellerUser', 'creator', 'order', 'product'];

    public function metadata()
    {
        $assignableUsers = User::query()
            ->whereIn('role', UserRole::sellableRoles())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values();

        return response()->json([
            'statuses' => WaitlistMetadata::STATUSES,
            'assignableUsers' => $assignableUsers,
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = WaitlistEntry::query()->with(self::EAGER_LOADS);

        // RN-02: quem não tem `canAccessAllRecords()` só vê as próprias
        // entradas — um `sellerUserId` de terceiro no filtro é ignorado
        // (mesmo padrão de `CommissionCalculator::scopedPaidOrderIds` /
        // `ShippingController::queue`), não misturado com o próprio.
        if (! $user->canAccessAllRecords()) {
            $query->where('seller_user_id', $user->id);
        } elseif ($request->filled('sellerUserId')) {
            $query->where('seller_user_id', $request->integer('sellerUserId'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('customerId')) {
            $query->where('customer_id', $request->integer('customerId'));
        }

        if ($request->filled('productId')) {
            $query->where('product_id', $request->integer('productId'));
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($builder) use ($search) {
                $builder->where('id', ctype_digit($search) ? (int) $search : -1)
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
            });
        }

        $entries = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(ApiPagination::perPage($request));

        return ApiPagination::response($entries, fn (WaitlistEntry $entry) => $this->toPayload($entry));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $status = $data['status'] ?? 'Pendente';

        if ($status === 'Convertido' && empty($data['orderId'])) {
            return response()->json([
                'message' => 'Uma entrada "Convertido" exige um pedido vinculado (orderId).',
            ], 422);
        }

        // Riscos da task: unicidade ativa por cliente/variação — só bloqueia
        // se já existir uma entrada do mesmo cliente+produto ainda ativa
        // (Pendente/Avisado). Entradas já Convertidas/Encerradas ficam como
        // histórico e não impedem uma nova espera futura.
        //
        // TASK-029: checagem e gravação passaram para dentro da mesma
        // transação, junto da auditoria. Isso não elimina a corrida entre
        // duas criações simultâneas — não há linha a bloquear antes de ela
        // existir; fechar isso exige índice único no banco, avaliado na
        // TASK-037.
        $entry = DB::transaction(function () use ($data, $request, $status) {
            $duplicate = WaitlistEntry::query()
                ->where('customer_id', $data['customerId'])
                ->where('product_id', $data['productId'] ?? null)
                ->whereIn('status', ['Pendente', 'Avisado'])
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'message' => 'Este cliente já está na lista de espera para este produto.',
                ], 422);
            }

            return $this->createEntry($request, $data, $status);
        }, 3);

        if ($entry instanceof \Illuminate\Http\JsonResponse) {
            return $entry;
        }

        return response()->json($this->toPayload($entry), 201);
    }

    private function createEntry(Request $request, array $data, string $status): WaitlistEntry
    {
        $entry = WaitlistEntry::create([
            'customer_id' => $data['customerId'],
            'product_id' => $data['productId'] ?? null,
            'product_name' => $data['productName'],
            'brand_name' => $data['brandName'] ?? null,
            'model_name' => $data['modelName'] ?? null,
            'quality_name' => $data['qualityName'] ?? null,
            // Default: quem registra assume como vendedor responsável, a
            // menos que outro seja explicitamente atribuído (ex.: gerente
            // registrando em nome de um vendedor) — sem esse default, a
            // própria pessoa que criou a entrada não conseguiria
            // editá-la/removê-la depois (RN-02 checa `seller_user_id`).
            'seller_user_id' => $data['sellerUserId'] ?? $request->user()->id,
            'created_by_user_id' => $request->user()->id,
            'status' => $status,
            'notes' => $data['notes'] ?? null,
            'notified_at' => $data['notifiedAt'] ?? null,
            'order_id' => $data['orderId'] ?? null,
        ]);

        $entry->load(self::EAGER_LOADS);

        $this->audit('waitlist.created', 'Entrada de lista de espera criada.', $entry, [
            'customer_id' => $entry->customer_id,
            'product_id' => $entry->product_id,
            'status' => $entry->status,
        ]);

        return $entry;
    }

    /**
     * TASK-029 — antes desta task este método não tinha transação nenhuma:
     * lia a entrada, checava ownership e a regra de status terminal, e
     * salvava, com a auditoria em seguida. Duas requisições concorrentes
     * podiam reabrir uma entrada terminal (as duas viam o status antigo) e
     * uma falha de auditoria deixava a mutação sem rastro.
     */
    public function update(Request $request, int $id)
    {
        $data = $this->validateData($request, true);

        $result = LockedTransaction::run(
            WaitlistEntry::query(),
            $id,
            fn (WaitlistEntry $entry) => $this->applyUpdate($request, $entry, $data),
        );

        return $result ?? response()->json(['message' => 'Entrada de lista de espera não encontrada.'], 404);
    }

    private function applyUpdate(Request $request, WaitlistEntry $entry, array $data)
    {
        $user = $request->user();

        // RN-02: ownership reavaliado sobre o registro bloqueado.
        if (! $user->canAccessAllRecords() && $entry->seller_user_id !== $user->id) {
            return response()->json(['message' => 'Você não tem permissão para editar esta entrada.'], 403);
        }

        $previousStatus = $entry->status;
        $newStatus = $data['status'] ?? $previousStatus;

        // Regra leve (task pede explicitamente para não formalizar uma
        // máquina de estados completa aqui): `Convertido`/`Encerrado` são
        // terminais — não voltam a `Pendente`/`Avisado`. Transitar entre os
        // dois terminais (ex.: corrigir um "Convertido" indevido para
        // "Encerrado") continua permitido.
        if (in_array($previousStatus, WaitlistMetadata::TERMINAL_STATUSES, true)
            && $newStatus !== $previousStatus
            && ! in_array($newStatus, WaitlistMetadata::TERMINAL_STATUSES, true)) {
            return response()->json([
                'message' => "Não é possível reabrir uma entrada '{$previousStatus}'.",
            ], 422);
        }

        $newOrderId = array_key_exists('orderId', $data) ? $data['orderId'] : $entry->order_id;

        if ($newStatus === 'Convertido' && empty($newOrderId)) {
            return response()->json([
                'message' => 'Uma entrada "Convertido" exige um pedido vinculado (orderId).',
            ], 422);
        }

        $fieldMap = [
            'customerId' => 'customer_id',
            'productId' => 'product_id',
            'productName' => 'product_name',
            'brandName' => 'brand_name',
            'modelName' => 'model_name',
            'qualityName' => 'quality_name',
            'sellerUserId' => 'seller_user_id',
            'status' => 'status',
            'notes' => 'notes',
            'notifiedAt' => 'notified_at',
            'orderId' => 'order_id',
        ];

        foreach ($fieldMap as $input => $column) {
            if (array_key_exists($input, $data)) {
                $entry->{$column} = $data[$input];
            }
        }

        $entry->save();
        $entry->load(self::EAGER_LOADS);

        $metadata = ['status' => $entry->status];
        if ($previousStatus !== $entry->status) {
            $metadata['previous_status'] = $previousStatus;
        }

        // RN-04: auditoria no mesmo commit da mutação.
        $this->audit('waitlist.updated', 'Entrada de lista de espera atualizada.', $entry, $metadata);

        return response()->json($this->toPayload($entry));
    }

    public function destroy(Request $request, int $id)
    {
        $result = LockedTransaction::run(
            WaitlistEntry::query(),
            $id,
            function (WaitlistEntry $entry) use ($request, $id) {
                $user = $request->user();

                if (! $user->canAccessAllRecords() && $entry->seller_user_id !== $user->id) {
                    return response()->json(['message' => 'Você não tem permissão para remover esta entrada.'], 403);
                }

                $entry->delete();
                $this->audit('waitlist.deleted', 'Entrada de lista de espera removida.', null, ['waitlist_entry_id' => $id]);

                return response()->json(['ok' => true]);
            },
        );

        return $result ?? response()->json(['message' => 'Entrada de lista de espera não encontrada.'], 404);
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $nullable = $partial ? 'sometimes' : 'nullable';

        return $request->validate([
            'customerId' => [$required, 'integer', Rule::exists('customers', 'id')],
            'productId' => [$nullable, 'integer', Rule::exists('products', 'id')],
            'productName' => [$required, 'string', 'max:255'],
            'brandName' => [$nullable, 'string', 'max:255'],
            'modelName' => [$nullable, 'string', 'max:255'],
            'qualityName' => [$nullable, 'string', 'max:255'],
            'sellerUserId' => [
                $nullable,
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', UserRole::sellableRoles())
                    ->where('is_active', true)),
            ],
            'status' => [$nullable, 'string', Rule::in(WaitlistMetadata::STATUSES)],
            'notes' => [$nullable, 'string'],
            'notifiedAt' => [$nullable, 'date'],
            'orderId' => [$nullable, 'integer', Rule::exists('orders', 'id')],
        ]);
    }

    /**
     * RN-03: sinal de disponibilidade só de leitura — `productCurrentQty` é
     * a quantidade atual em estoque do `product_id` (se o produto ainda
     * existir; `null` se foi excluído), `isAvailable` é `qty > 0`. Não
     * dispara alerta/notificação nenhuma (fora de escopo).
     */
    private function toPayload(WaitlistEntry $entry): array
    {
        /** @var Product|null $product */
        $product = $entry->relationLoaded('product') ? $entry->product : $entry->product()->first();
        $qty = $product?->qty;

        return [
            'id' => $entry->id,
            'customerId' => $entry->customer_id,
            'customerName' => $entry->customer?->name ?? '',
            'customerPhone' => $entry->customer?->phone ?? '',
            'productId' => $entry->product_id,
            'productName' => $entry->product_name,
            'brandName' => $entry->brand_name,
            'modelName' => $entry->model_name,
            'qualityName' => $entry->quality_name,
            'sellerUserId' => $entry->seller_user_id,
            'sellerUserName' => $entry->sellerUser?->name,
            'createdByUserId' => $entry->created_by_user_id,
            'createdByUserName' => $entry->creator?->name,
            'status' => $entry->status,
            'notes' => $entry->notes ?? '',
            'notifiedAt' => $entry->notified_at ?? '',
            'orderId' => $entry->order_id,
            'productCurrentQty' => $qty,
            'isAvailable' => $qty !== null && $qty > 0,
            'createdAt' => $entry->created_at?->toDateString() ?? '',
            'updatedAt' => $entry->updated_at?->toDateString() ?? '',
        ];
    }
}
