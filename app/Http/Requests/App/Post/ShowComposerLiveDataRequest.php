<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Post;

use Illuminate\Foundation\Http\FormRequest;

class ShowComposerLiveDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace !== null && $this->user()->can('createPost', $workspace);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
