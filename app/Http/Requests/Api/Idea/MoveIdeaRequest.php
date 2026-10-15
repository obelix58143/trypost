<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Idea;

use App\Support\Requests\Idea\IdeaRequestRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class MoveIdeaRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('update', $this->route('idea'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return IdeaRequestRules::move($this->user()->currentWorkspace);
    }
}
