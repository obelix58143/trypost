<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Repurpose;

use App\Models\Repurpose;
use App\Support\Requests\Repurpose\RepurposeRequestRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateRepurposeRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('update', $this->repurpose());
    }

    private function workspaceId(): ?string
    {
        return $this->user()->currentWorkspace?->id;
    }

    private function repurpose(): Repurpose
    {
        return $this->route('repurpose');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return RepurposeRequestRules::rules($this->workspaceId(), sourceRequired: false);
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
            $this->repurpose(),
        ));
    }
}
