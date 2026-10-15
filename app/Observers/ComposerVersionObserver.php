<?php

declare(strict_types=1);

namespace App\Observers;

use App\Http\Resources\App\HandleInertiaRequests\ComposerResource;
use Illuminate\Database\Eloquent\Model;

/**
 * Bumps the workspace's composer version whenever a row the composer bundle is
 * built from is saved or deleted, so edits within the same second still change
 * the `composer` once-prop key.
 */
class ComposerVersionObserver
{
    public function saved(Model $model): void
    {
        ComposerResource::bumpVersion((string) $model->getAttribute('workspace_id'));
    }

    public function deleted(Model $model): void
    {
        ComposerResource::bumpVersion((string) $model->getAttribute('workspace_id'));
    }
}
