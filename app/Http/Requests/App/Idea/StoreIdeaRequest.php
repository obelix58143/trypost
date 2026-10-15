<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Idea;

use App\Models\Idea;
use App\Support\Requests\Idea\IdeaRequestRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreIdeaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Idea::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return IdeaRequestRules::attributes($this->user()->currentWorkspace);
    }

    public function withValidator(Validator $validator): void
    {
        IdeaRequestRules::rejectEmptyIdea($validator, $this->all());
    }
}
