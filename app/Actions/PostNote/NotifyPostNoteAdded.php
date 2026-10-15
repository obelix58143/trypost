<?php

declare(strict_types=1);

namespace App\Actions\PostNote;

use App\Enums\Notification\Type;
use App\Enums\Post\Status;
use App\Jobs\SendNotification;
use App\Mail\PostNoteAdded;
use App\Models\PostNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class NotifyPostNoteAdded
{
    /**
     * Email every member of the post's workspace, except the note's author.
     * A note on a pending request reaches only those who can see it: the
     * approvers and the member who asked.
     */
    public static function execute(PostNote $note): void
    {
        $author = $note->user;
        $post = $note->post;
        $workspace = $post?->workspace;

        if (! $author instanceof User || ! $workspace) {
            return;
        }

        $requesterId = $post->status === Status::PendingApproval ? $post->approvalRequester()?->id : null;

        $workspace->members()
            ->with('account')
            ->where('users.id', '!=', $author->id)
            ->get()
            ->when(
                $post->status === Status::PendingApproval,
                fn (Collection $members): Collection => $members->filter(
                    fn (User $member): bool => $member->id === $requesterId || $member->publishesDirectlyThrough($workspace, $member->pivot),
                ),
            )
            ->each(fn (User $member) => SendNotification::dispatch(
                user: $member,
                type: Type::PostNoteAdded,
                mailable: new PostNoteAdded(note: $note, author: $author),
            ));
    }
}
