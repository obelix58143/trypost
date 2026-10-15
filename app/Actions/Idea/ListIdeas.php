<?php

declare(strict_types=1);

namespace App\Actions\Idea;

use App\Models\Idea;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

class ListIdeas
{
    public const BOARD_PAGE_SIZE = 10;

    /**
     * The gallery list: label and stage filters, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Idea>
     */
    public static function query(Workspace $workspace, array $filters = []): Builder
    {
        return self::applyStageFilter(self::filtered($workspace, $filters), $filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * One board column: label filter only (every stage is a column), by position.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Idea>
     */
    public static function column(Workspace $workspace, ?string $stageId, array $filters = []): Builder
    {
        return self::filtered($workspace, $filters)
            ->where('idea_stage_id', $stageId)
            ->orderBy('position')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @param  Builder<Idea>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Idea>
     */
    public static function applyLabelFilter(Builder $query, array $filters): Builder
    {
        $labelIds = (array) data_get($filters, 'labels', []);
        $untagged = (bool) data_get($filters, 'untagged', false);

        return $query->when($labelIds !== [] || $untagged, function (Builder $builder) use ($labelIds, $untagged): void {
            $builder->where(function (Builder $inner) use ($labelIds, $untagged): void {
                if ($labelIds !== []) {
                    $inner->whereHas('labels', fn (Builder $labels) => $labels->whereIn('workspace_labels.id', $labelIds));
                }

                if ($untagged) {
                    $inner->orWhereDoesntHave('labels');
                }
            });
        });
    }

    /**
     * Selected stages and "unassigned" combine with OR, like labels and "untagged".
     *
     * @param  Builder<Idea>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Idea>
     */
    private static function applyStageFilter(Builder $query, array $filters): Builder
    {
        $stageIds = (array) data_get($filters, 'stages', []);
        $unassigned = (bool) data_get($filters, 'unassigned', false);

        return $query->when($stageIds !== [] || $unassigned, function (Builder $builder) use ($stageIds, $unassigned): void {
            $builder->where(function (Builder $inner) use ($stageIds, $unassigned): void {
                if ($stageIds !== []) {
                    $inner->whereIn('idea_stage_id', $stageIds);
                }

                if ($unassigned) {
                    $inner->orWhereNull('idea_stage_id');
                }
            });
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Idea>
     */
    private static function filtered(Workspace $workspace, array $filters): Builder
    {
        return self::applyLabelFilter(
            Idea::query()->where('workspace_id', $workspace->id)->with('labels:id'),
            $filters,
        );
    }
}
