<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What one Pinterest or TikTok channel needs in the composer, read on each
 * open and per channel, so a slow network only holds its own settings.
 */
class ComposerAccountDataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pinterestBoards' => $this->when(array_key_exists('pinterestBoards', $this->resource), fn (): mixed => data_get($this->resource, 'pinterestBoards')),
            'tiktokCreatorInfo' => $this->when(array_key_exists('tiktokCreatorInfo', $this->resource), fn (): mixed => data_get($this->resource, 'tiktokCreatorInfo')),
        ];
    }
}
