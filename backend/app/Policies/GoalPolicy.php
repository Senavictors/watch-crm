<?php

namespace App\Policies;

use App\Models\Goal;
use App\Models\User;

class GoalPolicy
{
    public function view(User $user, Goal $goal): bool
    {
        if ($user->canAccessAllRecords()) {
            return true;
        }

        return $goal->scope === 'company' || $goal->target_user_id === $user->id;
    }

    public function update(User $user, Goal $goal): bool
    {
        return $user->canAccessAllRecords();
    }

    public function delete(User $user, Goal $goal): bool
    {
        return $user->canAccessAllRecords();
    }
}
