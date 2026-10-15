<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Channel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReorderChannelQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $gate = Gate::forUser($this->user());

        $gate->authorize('view', $this->route('account'));
        $gate->authorize('publishDirectly', $this->user()->currentWorkspace);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'post_ids' => ['required', 'array', 'max:500'],
            'post_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
