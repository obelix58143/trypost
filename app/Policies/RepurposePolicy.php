<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Repurpose;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RepurposePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentWorkspace !== null
            && $user->can('manageRepurposes', $user->currentWorkspace);
    }

    /**
     * A repurpose of another workspace is denied as not found, so its
     * existence does not leak across tenants.
     */
    public function view(User $user, Repurpose $repurpose): bool|Response
    {
        if ($repurpose->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('manageRepurposes', $user->currentWorkspace);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Repurpose $repurpose): bool|Response
    {
        return $this->view($user, $repurpose);
    }

    public function delete(User $user, Repurpose $repurpose): bool|Response
    {
        return $this->view($user, $repurpose);
    }
}
