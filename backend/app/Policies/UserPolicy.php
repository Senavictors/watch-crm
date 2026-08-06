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

        // Gerente não pode editar, bloquear ou redefinir senha de um admin ou
        // owner (TASK-013 — mesma restrição, papel do proprietário incluído)
        if (
            $actor->role === UserRole::Manager
            && in_array($target->role, [UserRole::Admin, UserRole::Owner], true)
        ) {
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
