<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFrictionNote;
use App\Models\User;
use App\Support\CustomerInsightsCalculator;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Customer::query()->orderBy('id');

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

        $customers = $query
            ->get()
            ->map(fn (Customer $customer) => $this->toPayload($customer));

        return response()->json($customers);
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

    public function destroy(int $id)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        $this->authorize('delete', $customer);
        $customer->delete();
        $this->audit('customers.deleted', 'Cliente removido.', null, ['customer_id' => $id]);

        return response()->json(['ok' => true]);
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
