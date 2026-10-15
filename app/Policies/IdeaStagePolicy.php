<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IdeaStage;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class IdeaStagePolicy
{
    public function create(User $user): bool
    {
        return $user->currentWorkspace !== null
            && $user->can('createPost', $user->currentWorkspace);
    }

    /**
     * A stage of another workspace is denied as not found, so its existence
     * does not leak across tenants.
     */
    public function update(User $user, IdeaStage $stage): bool|Response
    {
        if ($stage->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $this->create($user);
    }

    public function delete(User $user, IdeaStage $stage): bool|Response
    {
        return $this->update($user, $stage);
    }
}
