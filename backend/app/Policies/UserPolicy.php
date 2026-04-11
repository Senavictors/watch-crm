<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function update(User $actor, User $target): bool
    {
        if (! $actor->hasPermission('users.manage')) {
            return false;
        }

        // Gerente não pode editar, bloquear ou redefinir senha de um admin
        if ($actor->role === UserRole::Manager && $target->role === UserRole::Admin) {
            return false;
        }

        return true;
    }

    public function toggleActive(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }
}
