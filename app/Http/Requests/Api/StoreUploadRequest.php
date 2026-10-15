<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Support\Requests\Post\PostMediaRequestRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
