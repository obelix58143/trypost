<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

use App\Models\Post;
use App\Models\PostNote;
use App\Models\Workspace;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

trait ResolvesPostNotes
{
    /**
     * The post whose notes the user may read and write, with the web's checks:
     * the post is visible to them (a pending request they cannot see is not
     * found) and they may create posts in the workspace.
     */
    protected function notesPost(Request $request, Workspace $workspace, mixed $postId): Post|Response|ResponseFactory
    {
        $post = Post::query()->where('workspace_id', $workspace->id)->find($postId);

        if (! $post instanceof Post) {
            return Response::error('Post not found.');
        }

        return $this->denyUnlessCan($request, 'view', $post, 'Post not found.')
            ?? $this->denyUnlessCan($request, 'createPost', $workspace)
            ?? $post;
    }

    /**
     * A note of the post, or a tool error when the post has no such note.
     */
    protected function noteOfPost(Post $post, mixed $noteId): PostNote|Response|ResponseFactory
    {
        $note = $post->notes()->find($noteId);

        if (! $note instanceof PostNote) {
            return Response::error('Note not found.');
        }

        return $note;
    }
}
