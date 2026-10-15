<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The instants already held on one channel within the day the schedule
 * picker shows, as UTC strings.
 */
class ComposerTakenSlotsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'takenSlots' => $this->resource,
        ];
    }
}
