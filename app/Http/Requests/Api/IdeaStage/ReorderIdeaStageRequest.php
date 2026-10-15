<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\IdeaStage;

use App\Models\IdeaStage;
use App\Support\Requests\Idea\IdeaRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class ReorderIdeaStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', IdeaStage::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return IdeaRequestRules::reorderStages();
    }
}
