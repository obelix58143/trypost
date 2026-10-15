<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Post;

use App\Support\Requests\Post\PostRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class StorePostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return PostRequestRules::batch($this->user()->currentWorkspace);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PostRequestRules::messages();
    }
}
