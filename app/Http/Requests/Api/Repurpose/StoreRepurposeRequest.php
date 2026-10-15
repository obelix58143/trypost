<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Repurpose;

use App\Support\Requests\Repurpose\RepurposeRequestRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRepurposeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function workspaceId(): ?string
    {
        return $this->user()->currentWorkspace?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return RepurposeRequestRules::rules($this->workspaceId(), sourceRequired: true);
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => RepurposeRequestRules::addCrossFieldErrors(
            $validator,
            $this->workspaceId(),
            $this->all(),
        ));
    }
}
