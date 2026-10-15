<?php

declare(strict_types=1);

use App\Mcp\Concerns\AuthorizesMcpTool;
use App\Models\Post;
use App\Models\User;
use App\Models\Webhook;
use App\Models\Workspace;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function authorizesMcpToolProbe(): object
{
    return new class
    {
        use AuthorizesMcpTool;

        public function can(Request $request, string $ability, mixed $arguments, string $notFound = 'Not found.'): Response|ResponseFactory|null
        {
            return $this->denyUnlessCan($request, $ability, $arguments, $notFound);
        }

        public function workspace(Request $request, string $ability, mixed $arguments = null): Workspace|Response|ResponseFactory
        {
            return $this->authorizeCurrentWorkspace($request, $ability, $arguments);
        }
    };
}

function authorizesMcpToolRequest(?User $user): Request
{
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user);

    return $request;
}

function authorizesMcpToolWorkspace(string $access): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $workspace->members()->attach($owner->id, membershipPivot('admin'));
    $owner->update(['current_workspace_id' => $workspace->id]);

    return [$access === 'owner' ? $owner->fresh() : workspaceMember($workspace, $access), $workspace];
}

it('denies with the web authorization message when the mcp request has no authenticated user', function () {
    $denied = authorizesMcpToolProbe()->can(authorizesMcpToolRequest(null), 'view', Workspace::factory()->create());

    expect($denied)->toBeInstanceOf(Response::class)
        ->and($denied->isError())->toBeTrue()
        ->and((string) $denied->content())->toBe('This action is unauthorized.');
});

it('denies when the policy argument is null', function () {
    [$owner] = authorizesMcpToolWorkspace('owner');

    $denied = authorizesMcpToolProbe()->can(authorizesMcpToolRequest($owner), 'createPost', null);

    expect($denied)->toBeInstanceOf(Response::class)
        ->and((string) $denied->content())->toBe('This action is unauthorized.');
});

it('denies with the web authorization message when the user lacks the ability', function () {
    [$requester, $workspace] = authorizesMcpToolWorkspace('approval');

    $denied = authorizesMcpToolProbe()->can(authorizesMcpToolRequest($requester), 'publishDirectly', $workspace);

    expect($denied)->toBeInstanceOf(Response::class)
        ->and($denied->isError())->toBeTrue()
        ->and((string) $denied->content())->toBe('This action is unauthorized.');
});

it('answers not found when the policy denies as not found', function () {
    [$owner] = authorizesMcpToolWorkspace('owner');
    $foreign = Post::factory()->create(['workspace_id' => Workspace::factory()->create()->id]);

    $denied = authorizesMcpToolProbe()->can(authorizesMcpToolRequest($owner), 'view', $foreign, 'Post not found.');

    expect((string) $denied->content())->toBe('Post not found.');
});

it('allows when the user has the ability', function () {
    [$owner, $workspace] = authorizesMcpToolWorkspace('owner');

    expect(authorizesMcpToolProbe()->can(authorizesMcpToolRequest($owner), 'createPost', $workspace))->toBeNull();
});

it('authorizeCurrentWorkspace fails closed without a user', function () {
    $denied = authorizesMcpToolProbe()->workspace(authorizesMcpToolRequest(null), 'createPost');

    expect($denied)->toBeInstanceOf(Response::class)
        ->and($denied->isError())->toBeTrue()
        ->and((string) $denied->content())->toBe('This action is unauthorized.');
});

it('authorizeCurrentWorkspace denies when the user has no current workspace', function () {
    $user = User::factory()->create(['current_workspace_id' => null]);

    $denied = authorizesMcpToolProbe()->workspace(authorizesMcpToolRequest($user->fresh()), 'createPost');

    expect($denied)->toBeInstanceOf(Response::class)
        ->and((string) $denied->content())->toBe('This action is unauthorized.');
});

it('authorizeCurrentWorkspace denies when the user lacks the ability', function () {
    [$requester] = authorizesMcpToolWorkspace('approval');

    $denied = authorizesMcpToolProbe()->workspace(authorizesMcpToolRequest($requester), 'publishDirectly');

    expect($denied)->toBeInstanceOf(Response::class)
        ->and((string) $denied->content())->toBe('This action is unauthorized.');
});

it('authorizeCurrentWorkspace checks a class-level ability when given one', function () {
    [$admin, $workspace] = authorizesMcpToolWorkspace('admin');
    $member = workspaceMember($workspace, 'member');

    expect((string) authorizesMcpToolProbe()->workspace(authorizesMcpToolRequest($member), 'viewAny', Webhook::class)->content())->toBe('This action is unauthorized.')
        ->and(authorizesMcpToolProbe()->workspace(authorizesMcpToolRequest($admin), 'viewAny', Webhook::class)->is($workspace))->toBeTrue();
});

it('authorizeCurrentWorkspace returns the current workspace when allowed', function () {
    [$owner, $workspace] = authorizesMcpToolWorkspace('owner');

    $result = authorizesMcpToolProbe()->workspace(authorizesMcpToolRequest($owner), 'createPost');

    expect($result)->toBeInstanceOf(Workspace::class)
        ->and($result->is($workspace))->toBeTrue();
});
