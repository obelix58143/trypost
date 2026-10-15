<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Idea;

use App\Models\Idea;
use App\Support\Requests\Idea\IdeaRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyIdeasRequest extends FormRequest
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
        return IdeaRequestRules::bulk();
    }
}
