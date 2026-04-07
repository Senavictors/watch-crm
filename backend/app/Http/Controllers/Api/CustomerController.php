<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $query = Customer::query();

        if (! $user->canAccessAllRecords()) {
            $query->where('owner_user_id', $user->id);
        }

        $customers = $query
            ->orderBy('id')
            ->get()
            ->map(fn (Customer $customer) => $this->toPayload($customer));

        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
        ]);

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

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'instagram' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

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
            'ownerUserId' => $c->owner_user_id,
        ];
    }
}
