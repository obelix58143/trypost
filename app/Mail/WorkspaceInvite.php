<?php

declare(strict_types=1);

namespace App\Mail;

use App\Actions\Invite\ResolveInviteWorkspaces;
use App\Models\Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvite extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invite $invite
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.workspace_invite.subject', ['workspace' => $this->workspaceName()]),
        );
    }

    public function content(): Content
    {
        $workspaceName = $this->workspaceName();

        return new Content(
            view: 'mail.workspace-invite',
            with: [
                'title' => __('mail.workspace_invite.title', ['workspace' => $workspaceName]),
                'previewText' => __('mail.workspace_invite.preview', ['workspace' => $workspaceName]),
                'workspaceName' => $workspaceName,
                'isAdmin' => (bool) $this->invite->is_admin,
                'requiresApproval' => (bool) $this->invite->requires_approval,
                'url' => route('app.invites.show', $this->invite),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function workspaceName(): string
    {
        return ResolveInviteWorkspaces::execute($this->invite)->first()?->name ?? '';
    }
}
