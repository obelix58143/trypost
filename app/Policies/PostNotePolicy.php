<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PostNote;
use App\Models\User;

class PostNotePolicy
{
    /**
     * Only the author may edit a note.
     */
    public function update(User $user, PostNote $note): bool
    {
        return $note->user_id === $user->id;
    }

    /**
     * Only the author may delete a note.
     */
    public function delete(User $user, PostNote $note): bool
    {
        return $note->user_id === $user->id;
    }
}
