<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

use App\Models\Idea;
use App\Models\IdeaStage;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;

trait FindsIdeaRecords
{
    protected function findIdea(Request $request, string $key = 'idea_id'): ?Idea
    {
        $id = $request->get($key);

        return is_string($id) && Str::isUuid($id) ? Idea::query()->where('workspace_id', $request->user()?->current_workspace_id)->find($id) : null;
    }

    protected function findIdeaStage(Request $request): ?IdeaStage
    {
        $id = $request->get('idea_stage_id');

        return is_string($id) && Str::isUuid($id) ? IdeaStage::query()->where('workspace_id', $request->user()?->current_workspace_id)->find($id) : null;
    }
}
