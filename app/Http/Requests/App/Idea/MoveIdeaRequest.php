<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Idea;

use App\Models\Idea;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
        $placesAfterIdea = $this->placesAfterIdea();

        return [
            'idea_stage_id' => [
                'nullable',
                'uuid',
                Rule::exists('idea_stages', 'id')->where('workspace_id', $this->user()->currentWorkspace->id),
            ],
            'idea_ids' => [Rule::excludeIf($placesAfterIdea), 'required', 'array', 'max:'.Idea::MAX_BATCH],
            'idea_ids.*' => [Rule::excludeIf($placesAfterIdea), 'required', 'uuid', 'distinct'],
            'after_idea_id' => ['nullable', 'uuid'],
        ];
    }

    public function placesAfterIdea(): bool
    {
        return $this->exists('after_idea_id');
    }
}
