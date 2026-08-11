<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFrictionNote;
use App\Models\User;
use App\Support\ApiPagination;
use App\Support\CustomerInsightsCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = $this->visibleQuery($user);

        // TASK-025: arquivados ficam fora da lista por padrão; `archived=1`
        // mostra só eles, para consultar o histórico de um cliente antigo.
        if ($request->boolean('archived')) {
            $query->whereNotNull('archived_at');
        } else {
            $query->notArchived();
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('instagram', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name')->paginate(ApiPagination::perPage($request));

        return ApiPagination::response($customers, fn (Customer $customer) => $this->toPayload($customer));
    }

    public function lookup(Request $request)
    {
        // Cliente arquivado não aparece em novo pedido/devolução (TASK-025).
        $query = $this->visibleQuery($request->user())->notArchived();

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name')->limit(20)->get();

        return response()->json(['data' => $customers->map(fn (Customer $customer) => $this->toPayload($customer))->values()]);
    }

    private function visibleQuery(User $user)
    {
        $query = Customer::query();

        // TASK-019: bug de ownership corrigido — `index()` nunca filtrava
        // por dono, então qualquer usuário com `customers.view` (inclusive
        // vendedor) via nome/telefone/endereço de TODOS os clientes da
        // empresa. Diferente do padrão genérico usado em
        // Order/Return/Waitlist (`! $user->canAccessAllRecords()`), aqui o
        // filtro é restrito explicitamente a `role === Seller`: o papel
        // `garantia` também tem `customers.view` mas nunca tem
        // `customers.create` (não é dono de nenhum cliente) — filtrar por
        // `! canAccessAllRecords()` faria a garantia ver zero clientes e
        // quebraria o trabalho dela (precisa consultar contato/endereço de
        // clientes de qualquer vendedor para despachar reenvio).
        if ($user->getRoleName() === UserRole::Seller->value) {
            $query->where('owner_user_id', $user->id);
        }

        return $query;
    }

    public function show(Request $request, int $id)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        $this->authorize('view', $customer);

        $customer->load(['frictionNotes.createdBy']);

        return response()->json($this->toPayload($customer, $request->user()));
    }

    public function store(Request $request)
    {
        $data = $this->toDatabaseKeys($this->validatedData($request));

        if ($this->phoneAlreadyExists($data['phone'])) {
            return response()->json([
                'message' => 'Já existe um cliente cadastrado com este telefone.',
            ], 422);
        }

        $customer = Customer::create([
            ...$data,
            'owner_user_id' => $request->user()->id,
        ]);

        $this->audit('customers.created', 'Cliente criado.', $customer);

        return response()->json($this->toPayload($customer), 201);
    }

    public function update(Request $request, int $id)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        $this->authorize('update', $customer);

        $data = $this->toDatabaseKeys($this->validatedData($request, true));

        if (array_key_exists('phone', $data) && $this->phoneAlreadyExists($data['phone'], $customer->id)) {
            return response()->json([
                'message' => 'Já existe um cliente cadastrado com este telefone.',
            ], 422);
        }

        $customer->fill($data);
        $customer->save();
        $this->audit('customers.updated', 'Cliente atualizado.', $customer);

        return response()->json($this->toPayload($customer));
    }

    /**
     * TASK-025 (RN-01 / ADR-007): antes desta task, `customers.customer_id`
     * era CASCADE em `orders`, `returns` e `customer_friction_notes` —
     * excluir um cliente apagava toda a receita, as comissões e o pós-venda
     * dele. Agora só cliente sem nenhum histórico é excluído de verdade
     * (cadastro errado); o resto é arquivado.
     */
    public function destroy(int $id)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        $this->authorize('delete', $customer);

        $history = $customer->historyCounts();

        if ($history !== []) {
            $detail = collect($history)->map(fn (int $count, string $label) => "{$count} {$label}")->join(', ', ' e ');

            return response()->json([
                'message' => "Este cliente não pode ser excluído porque tem {$detail}. Arquive o cliente para tirá-lo das listagens sem perder o histórico.",
                'code' => 'customer_has_history',
                'history' => $history,
            ], 409);
        }

        DB::transaction(function () use ($customer, $id) {
            $customer->delete();
            $this->audit('customers.deleted', 'Cliente removido.', null, ['customer_id' => $id]);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Arquivar é visibilidade, não exclusão: o cliente sai das listagens e
     * dos lookups, e nenhum cálculo financeiro muda (ADR-007).
     */
    public function archive(Request $request, int $id)
    {
        return $this->setArchived($request, $id, true);
    }

    public function unarchive(Request $request, int $id)
    {
        return $this->setArchived($request, $id, false);
    }

    private function setArchived(Request $request, int $id, bool $archived)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        // Mesma autorização da exclusão: arquivar é a alternativa dela, não
        // uma edição corriqueira de cadastro.
        $this->authorize('delete', $customer);

        if ($customer->isArchived() === $archived) {
            return response()->json([
                'message' => $archived ? 'Cliente já está arquivado.' : 'Cliente não está arquivado.',
            ], 422);
        }

        DB::transaction(function () use ($customer, $request, $archived) {
            $customer->archived_at = $archived ? now() : null;
            $customer->archived_by_user_id = $archived ? $request->user()->id : null;
            $customer->save();

            $this->audit(
                $archived ? 'customers.archived' : 'customers.unarchived',
                $archived ? 'Cliente arquivado.' : 'Cliente desarquivado.',
                $customer,
            );
        });

        return response()->json($this->toPayload($customer));
    }

    public function addFrictionNote(Request $request, int $id)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        // Marcar atrito é uma ação de gestão de cliente — reaproveita a
        // mesma Policy/permissão de `update` (RN-03 não justifica uma
        // permissão nova).
        $this->authorize('update', $customer);

        $data = $request->validate([
            'note' => ['required', 'string', 'min:3'],
        ]);

        $note = CustomerFrictionNote::create([
            'customer_id' => $customer->id,
            'note' => $data['note'],
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->audit('customers.friction_note_added', 'Marcação de atrito adicionada ao cliente.', $customer, [
            'friction_note_id' => $note->id,
        ]);

        return response()->json($this->toFrictionNotePayload($note->load('createdBy')), 201);
    }

    private function phoneAlreadyExists(string $phone, ?int $exceptCustomerId = null): bool
    {
        return Customer::query()
            ->where('phone', $phone)
            ->when($exceptCustomerId !== null, fn ($query) => $query->where('id', '!=', $exceptCustomerId))
            ->exists();
    }

    /**
     * `insights`/`frictionNotes` só entram no payload quando `$user` é
     * informado (só `show()` chama com o segundo argumento) — `index()`
     * continua leve, sem recalcular insights de todo cliente listado.
     */
    private function toPayload(Customer $c, ?User $user = null): array
    {
        $payload = [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'email' => $c->email,
            'instagram' => $c->instagram,
            'street' => $c->street,
            'number' => $c->number,
            'complement' => $c->complement,
            'zipCode' => $c->zip_code,
            'city' => $c->city,
            'state' => $c->state,
            'ownerUserId' => $c->owner_user_id,
            'archivedAt' => $c->archived_at?->toIso8601String(),
        ];

        if ($user !== null) {
            $frictionNotes = ($c->relationLoaded('frictionNotes') ? $c->frictionNotes : $c->frictionNotes()->with('createdBy')->get())
                ->map(fn (CustomerFrictionNote $note) => $this->toFrictionNotePayload($note))
                ->values();

            $payload['insights'] = CustomerInsightsCalculator::build($user, $c);
            $payload['frictionNotes'] = $frictionNotes;
            $payload['hasFrictionHistory'] = $frictionNotes->isNotEmpty();
        }

        return $payload;
    }

    private function toFrictionNotePayload(CustomerFrictionNote $note): array
    {
        return [
            'id' => $note->id,
            'note' => $note->note,
            'createdByUserId' => $note->created_by_user_id,
            'createdByUserName' => $note->createdBy?->name,
            'createdAt' => $note->created_at?->toIso8601String(),
        ];
    }

    private function toDatabaseKeys(array $data): array
    {
        if (array_key_exists('zipCode', $data)) {
            $data['zip_code'] = $data['zipCode'];
            unset($data['zipCode']);
        }

        return $data;
    }

    private function validatedData(Request $request, bool $partial = false): array
    {
        $required = $partial ? ['sometimes'] : ['required'];
        $optional = $partial ? ['sometimes', 'nullable'] : ['nullable'];

        return $request->validate([
            'name' => [...$required, 'string', 'max:255'],
            'phone' => [...$required, 'string', 'max:50'],
            'email' => [...$optional, 'email', 'max:255'],
            'instagram' => [...$optional, 'string', 'max:255'],
            'street' => [...$optional, 'string', 'max:255'],
            'number' => [...$optional, 'string', 'max:20'],
            'complement' => [...$optional, 'string', 'max:255'],
            'zipCode' => [...$optional, 'string', 'max:20'],
            'city' => [...$optional, 'string', 'max:100'],
            'state' => [...$optional, 'string', 'max:2'],
        ]);
    }
}
