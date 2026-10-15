<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

trait AuthorizesMcpTool
{
    /**
     * Run the same Gate check as the matching web route. Returns the web's refusal
     * ("This action is unauthorized.", or $notFound when the policy denies as not
     * found) as a tool error, or null when allowed. Fails closed without a user.
     */
    protected function denyUnlessCan(
        Request $request,
        string $ability,
        mixed $arguments = [],
        string $notFound = 'Not found.',
    ): Response|ResponseFactory|null {
        try {
            Gate::forUser($request->user())->authorize($ability, $arguments);
        } catch (AuthorizationException $exception) {
            return Response::error($exception->status() === HttpResponse::HTTP_NOT_FOUND ? $notFound : $exception->getMessage());
        }

        return null;
    }

    /**
     * The workspace the MCP token is bound to, or a tool error when there is none.
     */
    protected function currentWorkspace(Request $request): Workspace|Response|ResponseFactory
    {
        $workspace = $request->user()?->currentWorkspace;

        if (! $workspace instanceof Workspace) {
            return Response::error((new AuthorizationException)->getMessage());
        }

        return $workspace;
    }

    /**
     * Resolve the current workspace and authorize $ability on it, or on $arguments
     * when the web checks a class-level policy (e.g. `viewAny`, Webhook::class).
     */
    protected function authorizeCurrentWorkspace(
        Request $request,
        string $ability,
        mixed $arguments = null,
    ): Workspace|Response|ResponseFactory {
        $workspace = $this->currentWorkspace($request);

        if (! $workspace instanceof Workspace) {
            return $workspace;
        }

        return $this->denyUnlessCan($request, $ability, $arguments ?? $workspace) ?? $workspace;
    }
}
