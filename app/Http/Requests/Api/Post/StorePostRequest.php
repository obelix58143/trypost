<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Post;

use App\Enums\SocialAccount\Platform;
use App\Models\SocialAccount;
use App\Support\Requests\Post\PostRequestRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return PostRequestRules::store($this->user()->currentWorkspace, $this->all());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PostRequestRules::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return PostRequestRules::attributes();
    }

    /**
     * @return Collection<int, Platform>
     */
    public function selectedPlatforms(): Collection
    {
        return PostRequestRules::selectedAccounts($this->user()->currentWorkspace, $this->all())
            ->map(fn (SocialAccount $account): Platform => $account->platform)
            ->values();
    }
}
