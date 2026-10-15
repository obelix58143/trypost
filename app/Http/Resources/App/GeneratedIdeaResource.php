<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An idea suggested by the model and not stored yet.
 */
class GeneratedIdeaResource extends JsonResource
{
    /**
     * @return array{title: string, body: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => (string) data_get($this->resource, 'title'),
            'body' => (string) data_get($this->resource, 'body'),
        ];
    }
}
