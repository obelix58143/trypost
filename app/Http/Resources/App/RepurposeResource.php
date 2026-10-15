<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use App\Http\Resources\Api\RepurposeResource as ApiRepurposeResource;
use Illuminate\Http\Request;

class RepurposeResource extends ApiRepurposeResource
{
    /**
     * The API shape, with the source account in the app's shape so the page can
     * render its avatar.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'source_account' => $this->whenLoaded('sourceAccount', fn () => $this->sourceAccount ? new SocialAccountResource($this->sourceAccount) : null),
        ];
    }
}
