<?php

declare(strict_types=1);

namespace App\Actions\Idea;

use App\Models\Idea;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveIdea
{
    /**
     * @param  list<string>  $orderedIdeaIds
     */
    public static function execute(Idea $idea, ?string $stageId, array $orderedIdeaIds): void
    {
        DB::transaction(function () use ($idea, $stageId, $orderedIdeaIds): void {
            $locked = Idea::query()
                ->where('workspace_id', $idea->workspace_id)
                ->where(function ($query) use ($idea, $stageId): void {
                    $query->whereKey($idea->id)->orWhere('idea_stage_id', $stageId);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('idea_stage_id', 'id');

            if (! $locked->has($idea->id)) {
                throw (new ModelNotFoundException)->setModel(Idea::class, [$idea->id]);
            }

            $expected = $locked->keys()->all();
            $requested = array_values($orderedIdeaIds);

            if (count($requested) !== count($expected) || array_diff($expected, $requested) !== [] || array_diff($requested, $expected) !== []) {
                throw ValidationException::withMessages([
                    'idea_ids' => __('create.ideas.errors.stale_idea_order'),
                ]);
            }

            $idea->update(['idea_stage_id' => $stageId]);

            foreach ($requested as $position => $id) {
                Idea::query()->whereKey($id)->update(['position' => $position]);
            }
        });
    }

    /**
     * Places the idea right after another idea of the stage, or first when none is given.
     */
    public static function after(Idea $idea, ?string $stageId, ?string $afterIdeaId): void
    {
        DB::transaction(function () use ($idea, $stageId, $afterIdeaId): void {
            $locked = Idea::query()
                ->where('workspace_id', $idea->workspace_id)
                ->where(function ($query) use ($idea, $stageId): void {
                    $query->whereKey($idea->id)->orWhere('idea_stage_id', $stageId);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'idea_stage_id', 'position', 'created_at']);

            if (! $locked->contains('id', $idea->id)) {
                throw (new ModelNotFoundException)->setModel(Idea::class, [$idea->id]);
            }

            $siblings = $locked
                ->reject(fn (Idea $candidate): bool => $candidate->id === $idea->id)
                ->sortBy([['position', 'asc'], ['created_at', 'asc'], ['id', 'asc']])
                ->pluck('id')
                ->values();

            $anchor = $afterIdeaId === null ? -1 : $siblings->search($afterIdeaId, true);

            if ($anchor === false) {
                throw ValidationException::withMessages([
                    'after_idea_id' => __('create.ideas.errors.stale_idea_order'),
                ]);
            }

            $ordered = $siblings->take($anchor + 1)->push($idea->id)->concat($siblings->slice($anchor + 1));
            $positions = $locked->pluck('position', 'id');

            $idea->update(['idea_stage_id' => $stageId]);

            foreach ($ordered->values() as $position => $id) {
                if ($positions->get($id) !== $position) {
                    Idea::query()->whereKey($id)->update(['position' => $position]);
                }
            }
        });
    }
}
