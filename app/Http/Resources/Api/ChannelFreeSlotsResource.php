<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChannelFreeSlotsResource extends JsonResource
{
    /**
     * @return array{slots: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'slots' => collect($this->resource)
                ->map(fn (CarbonInterface $slot): string => $slot->toIso8601ZuluString())
                ->values()
                ->all(),
        ];
    }
}
