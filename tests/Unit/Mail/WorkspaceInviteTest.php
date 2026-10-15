<?php

declare(strict_types=1);

use App\Mail\WorkspaceInvite;
use App\Models\Account;
use App\Models\Invite;
use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;

function workspaceInviteFor(string $workspaceName): Invite
{
    $account = Account::factory()->create(['name' => "Ada's Account"]);
    $workspace = Workspace::factory()->create(['account_id' => $account->id, 'name' => $workspaceName]);

    return Invite::factory()->create([
        'account_id' => $account->id,
        'workspaces' => [$workspace->id],
    ]);
}

test('workspace invite mail subject names the workspace, not the account', function () {
    $mail = new WorkspaceInvite(workspaceInviteFor('Marketing'));

    expect($mail->envelope()->subject)->toBe("You've been invited to join Marketing");
});

test('workspace invite mail has correct content', function () {
    $invite = workspaceInviteFor('My Team');

    $mail = new WorkspaceInvite($invite);
    $content = $mail->content();

    expect($content->view)->toBe('mail.workspace-invite');
    expect($content->with['title'])->toBe("You've been invited to join My Team");
    expect($content->with['previewText'])->toBe("You've been invited to join My Team");
    expect($content->with['workspaceName'])->toBe('My Team');
    expect($content->with)->not->toHaveKey('accountName');
    expect($content->with['isAdmin'])->toBeFalse();
    expect($content->with['requiresApproval'])->toBeFalse();
    expect($content->with['url'])->toBe(route('app.invites.show', $invite->id));
});

test('workspace invite mail has no attachments', function () {
    $invite = Invite::factory()->create();

    $mail = new WorkspaceInvite($invite);

    expect($mail->attachments())->toBeEmpty();
});

test('workspace invite mail is queueable', function () {
    $invite = Invite::factory()->create();

    $mail = new WorkspaceInvite($invite);

    expect($mail)->toBeInstanceOf(ShouldQueue::class);
});

test('footer has no manage-notifications or unsubscribe link — this is a transactional email, not preference-driven', function () {
    $invite = Invite::factory()->create();

    $mail = new WorkspaceInvite($invite);

    $mail->assertDontSeeInHtml('Manage notifications');
    $mail->assertDontSeeInHtml('Unsubscribe');
});
