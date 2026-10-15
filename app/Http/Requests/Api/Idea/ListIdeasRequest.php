<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Idea;

use App\Models\Idea;
use App\Support\Requests\Idea\IdeaRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class ListIdeasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Idea::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return IdeaRequestRules::filters();
    }

    /**
     * @return array{stages: list<string>, labels: list<string>, untagged: bool, unassigned: bool}
     */
    public function filters(): array
    {
        return IdeaRequestRules::filterValues($this->query());
    }
}
