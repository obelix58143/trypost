<?php

declare(strict_types=1);

namespace App\Actions\Signature;

use App\Models\Workspace;
use App\Models\WorkspaceSignature;
use Illuminate\Database\Eloquent\Builder;

class ListSignatures
{
    /**
     * @return Builder<WorkspaceSignature>
     */
    public static function execute(Workspace $workspace, ?string $search = null): Builder
    {
        return WorkspaceSignature::query()
            ->where('workspace_id', $workspace->id)
            ->when($search, fn (Builder $query, string $term) => $query->whereLike('name', "%{$term}%"))
            ->latest()
            ->orderByDesc('id');
    }
}
