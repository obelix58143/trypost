<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Post;

use App\Support\Requests\Post\PostMediaRequestRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('update', $this->route('post'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return PostMediaRequestRules::file();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($violation = PostMediaRequestRules::fileViolation($this->file('media'))) {
                $validator->errors()->add('media', $violation);
            }
        });
    }
}
