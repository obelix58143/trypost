<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Http\Resources\App\ChannelPostingScheduleResource as AppChannelPostingScheduleResource;
use Illuminate\Http\Request;

class ChannelPostingScheduleResource extends AppChannelPostingScheduleResource
{
    /**
     * @return array{id: string, timezone: string, posting_goal: int|null, posting_schedule: array<int, mixed>|null}
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, ...parent::toArray($request)];
    }
}
