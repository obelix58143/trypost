<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Repurpose;

use App\Models\Repurpose;
use App\Support\Repurpose\RepurposeRules;
use App\Support\Requests\Repurpose\RepurposeRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreRepurposeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Repurpose::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return RepurposeRules::settings($this->user()->current_workspace_id, sourceRequired: true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return RepurposeRequestRules::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return RepurposeRequestRules::attributes();
    }
}
