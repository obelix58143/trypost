<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Post;

use App\Support\Requests\Post\PostMediaRequestRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AttachMediaFromUrlRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('update', $this->route('post'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return PostMediaRequestRules::attachFromUrl();
    }
}
