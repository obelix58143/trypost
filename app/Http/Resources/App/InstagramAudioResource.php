<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstagramAudioResource extends JsonResource
{
    /**
     * @return array{id: string, type: string|null, title: string, artist: string|null, cover_url: string|null, duration_ms: int|null, preview_url: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) data_get($this->resource, 'id'),
            'type' => data_get($this->resource, 'type'),
            'title' => (string) data_get($this->resource, 'title'),
            'artist' => data_get($this->resource, 'artist'),
            'cover_url' => data_get($this->resource, 'cover_url'),
            'duration_ms' => data_get($this->resource, 'duration_ms'),
            'preview_url' => data_get($this->resource, 'preview_url'),
        ];
    }
}
