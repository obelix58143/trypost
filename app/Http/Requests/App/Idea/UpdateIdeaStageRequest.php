<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Idea;

use App\Support\Requests\Idea\IdeaRequestRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateIdeaStageRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('update', $this->route('ideaStage'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return IdeaRequestRules::updateStage();
    }
}
