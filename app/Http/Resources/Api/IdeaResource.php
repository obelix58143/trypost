<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Idea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Idea
 */
class IdeaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idea_stage_id' => $this->idea_stage_id,
            'title' => $this->title,
            'body' => $this->body,
            'position' => $this->position,
            'media' => $this->media ?? [],
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
