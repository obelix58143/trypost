<?php

declare(strict_types=1);

namespace App\Actions\Label;

use App\Models\Workspace;
use App\Models\WorkspaceLabel;
use Illuminate\Database\Eloquent\Builder;

class ListLabels
{
    /**
     * @return Builder<WorkspaceLabel>
     */
    public static function execute(Workspace $workspace, ?string $search = null): Builder
    {
        return WorkspaceLabel::query()
            ->where('workspace_id', $workspace->id)
            ->when($search, fn (Builder $query, string $term) => $query->whereLike('name', "%{$term}%"))
            ->latest()
            ->orderByDesc('id');
    }
}
