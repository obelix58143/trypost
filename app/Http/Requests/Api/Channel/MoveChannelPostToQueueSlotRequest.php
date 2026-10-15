<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Channel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class MoveChannelPostToQueueSlotRequest extends FormRequest
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
            'post_id' => ['required', 'uuid'],
            'slot_at' => ['required', 'date'],
        ];
    }
}
