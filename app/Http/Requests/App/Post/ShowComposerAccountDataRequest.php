<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Post;

use App\Models\SocialAccount;
use Illuminate\Foundation\Http\FormRequest;

class ShowComposerAccountDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;
        $account = $this->route('account');

        return $workspace !== null
            && $account instanceof SocialAccount
            && $account->workspace_id === $workspace->id
            && $this->user()->can('createPost', $workspace);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
