<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Label;

use App\Support\Requests\Label\LabelRequestRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLabelRequest extends FormRequest
{
    public function authorize(): Response
    {
        return $this->route('label')->workspace_id === $this->user()->currentWorkspace?->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return LabelRequestRules::rules();
    }
}
