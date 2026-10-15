<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Post;

use App\Support\RequestIds;
use Illuminate\Foundation\Http\FormRequest;

class ListPostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ids that are not UUIDs are dropped and ids of another workspace match nothing, like the publish page filters.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channels' => ['sometimes', 'nullable', 'array'],
            'labels' => ['sometimes', 'nullable', 'array'],
            'untagged' => ['sometimes', 'nullable'],
        ];
    }

    /**
     * @return array{channels: list<string>, labels: list<string>, untagged: bool}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'channels' => RequestIds::uuidList(collect((array) data_get($validated, 'channels'))->unique()),
            'labels' => RequestIds::uuidList(collect((array) data_get($validated, 'labels'))),
            'untagged' => filter_var(data_get($validated, 'untagged'), FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
