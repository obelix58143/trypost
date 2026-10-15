<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\PostNote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class UpdatePostNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->route('note')->post_id === $this->route('post')->id, Response::HTTP_NOT_FOUND);

        $gate = Gate::forUser($this->user());

        $gate->authorize('view', $this->route('post'));
        $gate->authorize('createPost', $this->route('post')->workspace);
        $gate->authorize('update', $this->route('note'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
