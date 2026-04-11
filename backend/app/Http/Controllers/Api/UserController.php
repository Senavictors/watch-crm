<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->toPayload($user));

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
            'role'     => ['required', 'string', Rule::in(array_column(UserRole::cases(), 'value'))],
        ]);

        // Gerente não pode criar usuários admin
        if (
            $request->user()->role === UserRole::Manager
            && $data['role'] === UserRole::Admin->value
        ) {
            abort(403, 'Gerentes não podem criar usuários administradores.');
        }

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'role'      => $data['role'],
            'is_active' => true,
        ]);

        $this->audit('users.created', 'Usuário criado.', $user);

        return response()->json($this->toPayload($user), 201);
    }

    public function update(Request $request, int $id)
    {
        $target = User::find($id);

        if (! $target) {
            return response()->json(['message' => 'Usuário não encontrado.'], 404);
        }

        $this->authorize('update', $target);

        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'role'  => ['sometimes', 'string', Rule::in(array_column(UserRole::cases(), 'value'))],
        ]);

        // Gerente não pode atribuir role admin
        if (
            isset($data['role'])
            && $request->user()->role === UserRole::Manager
            && $data['role'] === UserRole::Admin->value
        ) {
            abort(403, 'Gerentes não podem atribuir a função de administrador.');
        }

        $target->fill($data)->save();
        $this->audit('users.updated', 'Usuário atualizado.', $target);

        return response()->json($this->toPayload($target));
    }

    public function toggleActive(Request $request, int $id)
    {
        $target = User::find($id);

        if (! $target) {
            return response()->json(['message' => 'Usuário não encontrado.'], 404);
        }

        if ($target->id === $request->user()->id) {
            return response()->json(['message' => 'Você não pode bloquear sua própria conta.'], 422);
        }

        $this->authorize('toggleActive', $target);

        $target->forceFill(['is_active' => ! $target->is_active])->save();

        $this->audit('users.toggled_active', 'Status do usuário alterado.', $target, [
            'is_active' => $target->is_active,
        ]);

        return response()->json($this->toPayload($target));
    }

    public function resetPassword(Request $request, int $id)
    {
        $target = User::find($id);

        if (! $target) {
            return response()->json(['message' => 'Usuário não encontrado.'], 404);
        }

        $this->authorize('resetPassword', $target);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:12'],
        ]);

        $target->forceFill(['password' => $data['password']])->save();

        $this->audit('users.password_reset', 'Senha redefinida.', null, [
            'target_user_id' => $target->id,
        ]);

        return response()->json(['ok' => true]);
    }

    private function toPayload(User $user): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'role'        => $user->getRoleName(),
            'isActive'    => $user->is_active,
            'lastLoginAt' => optional($user->last_login_at)->toIso8601String(),
        ];
    }
}
