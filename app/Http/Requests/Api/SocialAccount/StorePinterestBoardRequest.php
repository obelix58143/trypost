<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\SocialAccount;

use App\Enums\SocialAccount\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class StorePinterestBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $gate = Gate::forUser($this->user());

        $gate->authorize('view', $this->route('account'));

        abort_unless($this->route('account')->platform === Platform::Pinterest, Response::HTTP_NOT_FOUND);

        $gate->authorize('createPost', $this->user()->currentWorkspace);

        return true;
    }

    /**
     * Pinterest caps board names at 50 characters.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }
}
