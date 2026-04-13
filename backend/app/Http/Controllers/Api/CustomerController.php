<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Customer $customer) => $this->toPayload($customer));

        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $data = $this->toDatabaseKeys($this->validatedData($request));

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

    private function toPayload(Customer $c): array
    {
        return [
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
