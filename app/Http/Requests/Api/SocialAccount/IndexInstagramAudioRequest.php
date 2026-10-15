<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\SocialAccount;

use App\Support\Social\InstagramAudio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexInstagramAudioRequest extends FormRequest
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
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', Rule::in(InstagramAudio::TYPES)],
        ];
    }
}
