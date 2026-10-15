<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Idea;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class IdeaPolicy
{
    public function create(User $user): bool
    {
        return $user->currentWorkspace !== null
            && $user->can('createPost', $user->currentWorkspace);
    }

    public function viewAny(User $user): bool
    {
        return $this->create($user);
    }

    public function view(User $user, Idea $idea): bool|Response
    {
        return $this->update($user, $idea);
    }

    /**
     * An idea of another workspace is denied as not found, so its existence
     * does not leak across tenants.
     */
    public function update(User $user, Idea $idea): bool|Response
    {
        if ($idea->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $this->create($user);
    }

    public function delete(User $user, Idea $idea): bool|Response
    {
        return $this->update($user, $idea);
    }
}
