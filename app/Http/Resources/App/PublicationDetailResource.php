<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A network publication with its latest metrics, for one that has no TryPost
 * post to show (see ReadPublicationAnalytics::latestForWorkspacePublication).
 */
class PublicationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
