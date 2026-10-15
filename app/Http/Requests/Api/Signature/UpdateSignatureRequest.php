<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Signature;

use App\Support\Requests\Signature\SignatureRequestRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSignatureRequest extends FormRequest
{
    public function authorize(): Response
    {
        return $this->route('signature')->workspace_id === $this->user()->currentWorkspace?->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return SignatureRequestRules::rules();
    }
}
