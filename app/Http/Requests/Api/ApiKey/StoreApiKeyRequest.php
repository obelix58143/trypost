<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\ApiKey;

use App\Support\Requests\ApiKey\ApiKeyRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $workspace = $user?->currentWorkspace;

        return $workspace !== null
            && $user->can('manageTeam', $workspace);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ApiKeyRequestRules::store();
    }
}
