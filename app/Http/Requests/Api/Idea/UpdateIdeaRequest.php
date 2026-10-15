<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Idea;

use App\Models\Idea;
use App\Support\Requests\Idea\IdeaRequestRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateIdeaRequest extends FormRequest
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
        /** @var Idea $idea */
        $idea = $this->route('idea');

        return IdeaRequestRules::attributes(
            $this->user()->currentWorkspace,
            collect($idea->media ?? [])->pluck('id')->filter()->values()->all(),
        );
    }

    public function withValidator(Validator $validator): void
    {
        IdeaRequestRules::rejectEmptyIdea($validator, $this->all(), $this->route('idea'));
    }
}
