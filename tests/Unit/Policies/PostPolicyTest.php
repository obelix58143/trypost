<?php

declare(strict_types=1);

use App\Enums\Post\Status;
use App\Models\Account;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\PostPolicy;
use Illuminate\Auth\Access\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

beforeEach(function () {
    $this->policy = new PostPolicy;
});

/**
 * Build a post + an actor with the given workspace access, both in one account.
 *
 * @return array{0: User, 1: Post}
 */
function postPolicyActor(string $access): array
{
    $account = Account::factory()->create();
    $owner = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $owner->id]);
    $workspace = Workspace::factory()->create(['account_id' => $account->id, 'user_id' => $owner->id]);
    $post = Post::factory()->create(['workspace_id' => $workspace->id]);

    $actor = match ($access) {
        'owner' => $owner,
        'outsider' => User::factory()->create(['account_id' => $account->id]),
        default => tap(User::factory()->create(['account_id' => $account->id]), fn (User $user) => $workspace->members()->attach($user->id, membershipPivot($access))),
    };

    $actor->update(['current_workspace_id' => $workspace->id]);

    return [$actor->refresh(), $post];
}

test('every workspace member can view a post', function (string $access) {
    [$actor, $post] = postPolicyActor($access);

    expect($this->policy->view($actor, $post))->toBeTrue();
})->with(['owner', 'admin', 'member', 'approval']);

test('post update/delete/duplicate is allowed for every member and denied outside the workspace', function (string $access, bool $allowed, bool $deletes) {
    [$actor, $post] = postPolicyActor($access);

    expect($this->policy->update($actor, $post))->toBe($allowed);
    expect($this->policy->delete($actor, $post))->toBe($deletes);
    expect($this->policy->duplicate($actor, $post))->toBe($allowed);
})->with([
    'owner' => ['owner', true, true],
    'admin' => ['admin', true, true],
    'member' => ['member', true, true],
    'needs approval' => ['approval', true, false],
    'outsider' => ['outsider', false, false],
]);

test('a member who needs approval deletes only the posts they wrote or requested while still pending', function (string $relation, bool $allowed) {
    [$actor, $post] = postPolicyActor('approval');
    $post->update(match ($relation) {
        'author' => ['user_id' => $actor->id],
        'requester' => ['approval_requested_by' => $actor->id, 'status' => Status::PendingApproval],
        'requester once approved' => ['approval_requested_by' => $actor->id, 'status' => Status::Scheduled],
        'author, another member requested' => ['user_id' => $actor->id, 'approval_requested_by' => $post->workspace->user_id],
        'another member' => [],
    });

    expect($this->policy->delete($actor, $post->fresh()))->toBe($allowed);
})->with([
    'author' => ['author', true],
    'requester' => ['requester', true],
    'requester once approved' => ['requester once approved', false],
    'author, another member requested' => ['author, another member requested', true],
    'another member' => ['another member', false],
]);

test('another member pending request is hidden as not found from a member who cannot approve it', function (string $access, bool $visible) {
    [$actor, $post] = postPolicyActor($access);
    $post->update(['status' => Status::PendingApproval]);

    foreach (['view', 'update', 'delete', 'duplicate'] as $ability) {
        $result = $this->policy->{$ability}($actor, $post->fresh());

        expect($visible ? $result === true : $result instanceof Response && $result->status() === HttpResponse::HTTP_NOT_FOUND)->toBeTrue();
    }
})->with([
    'owner' => ['owner', true],
    'admin' => ['admin', true],
    'member who publishes directly' => ['member', true],
    'needs approval' => ['approval', false],
]);

test('a member who needs approval still reaches the pending request they asked for', function () {
    [$actor, $post] = postPolicyActor('approval');
    $post->update(['status' => Status::PendingApproval, 'approval_requested_by' => $actor->id]);

    expect($this->policy->view($actor, $post->fresh()))->toBeTrue()
        ->and($this->policy->update($actor, $post->fresh()))->toBeTrue()
        ->and($this->policy->delete($actor, $post->fresh()))->toBeTrue();
});
