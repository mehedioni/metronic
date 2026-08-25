<?php

namespace Modules\Inventory\Policies;

use App\Core\Support\Permissions;
use App\Models\User;
use Modules\Inventory\Models\Expense;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::EXPENSES_VIEW);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can(Permissions::EXPENSES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::EXPENSES_CREATE);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->can(Permissions::EXPENSES_UPDATE);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->can(Permissions::EXPENSES_DELETE);
    }
}
