<?php

declare(strict_types=1);

namespace App\Support\Requests\Idea;

use App\Actions\Media\ResolveWorkspaceMedia;
use App\Models\Idea;
use App\Models\Workspace;
use App\Support\AiPromptRules;
use App\Support\RequestIds;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Single source of the idea and idea stage rules shared by the web FormRequests
 * and the API and MCP entry points.
 */
class IdeaRequestRules
{
    /**
     * A media id is valid when a save in the workspace may use it (see
     * ResolveWorkspaceMedia). Ids already stored on the idea stay valid even
     * when their row is gone; SyncOwnedMedia keeps those items as stored.
     *
     * @param  list<string>  $storedMediaIds
     * @return array<string, mixed>
     */
    public static function attributes(Workspace $workspace, array $storedMediaIds = []): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:'.AiPromptRules::PROMPT_MAX_LENGTH],
            'idea_stage_id' => self::stageId($workspace),
            'media_ids' => ['sometimes', 'array', 'max:'.Idea::MAX_MEDIA],
            'media_ids.*' => [
                'uuid',
                'distinct',
                function (string $attribute, mixed $value, Closure $fail) use ($workspace, $storedMediaIds): void {
                    if (! is_string($value) || ! Str::isUuid($value) || in_array($value, $storedMediaIds, true)) {
                        return;
                    }

                    if (ResolveWorkspaceMedia::execute($workspace, [$value])->isEmpty()) {
                        $fail('validation.exists')->translate();
                    }
                },
            ],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => [
                'uuid',
                Rule::exists('workspace_labels', 'id')
                    ->where('workspace_id', $workspace->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function emptyIdeaViolation(array $input, ?Idea $idea = null): ?string
    {
        $title = array_key_exists('title', $input) ? data_get($input, 'title') : $idea?->title;
        $body = array_key_exists('body', $input) ? data_get($input, 'body') : $idea?->body;
        $media = array_key_exists('media_ids', $input) ? data_get($input, 'media_ids') : ($idea?->media ?? []);

        if (blank($title) && blank($body) && blank($media)) {
            return __('create.ideas.errors.empty');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function rejectEmptyIdea(Validator $validator, array $input, ?Idea $idea = null): void
    {
        $validator->after(function (Validator $validator) use ($input, $idea): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $violation = self::emptyIdeaViolation($input, $idea);

            if ($violation !== null) {
                $validator->errors()->add('title', $violation);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function storeStage(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function updateStage(): array
    {
        return self::storeStage();
    }

    /**
     * @return array<string, mixed>
     */
    public static function reorderStages(): array
    {
        return [
            'stage_ids' => ['required', 'array'],
            'stage_ids.*' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function move(Workspace $workspace): array
    {
        return [
            'idea_stage_id' => self::stageId($workspace),
            ...self::bulk(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function bulk(): array
    {
        return [
            'idea_ids' => ['required', 'array', 'max:'.Idea::MAX_BATCH],
            'idea_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function list(): array
    {
        return [
            'view' => ['sometimes', 'nullable', 'string'],
            'stage' => ['sometimes', 'nullable', 'string'],
            ...self::filters(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filters(): array
    {
        return [
            'stages' => ['sometimes', 'nullable', 'array'],
            'labels' => ['sometimes', 'nullable', 'array'],
            'untagged' => ['sometimes', 'nullable'],
            'unassigned' => ['sometimes', 'nullable'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{stages: list<string>, labels: list<string>, untagged: bool, unassigned: bool}
     */
    public static function filterValues(array $input): array
    {
        return [
            'stages' => RequestIds::uuidList(collect((array) data_get($input, 'stages'))),
            'labels' => RequestIds::uuidList(collect((array) data_get($input, 'labels'))),
            'untagged' => filter_var(data_get($input, 'untagged'), FILTER_VALIDATE_BOOLEAN),
            'unassigned' => filter_var(data_get($input, 'unassigned'), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function stageId(Workspace $workspace): array
    {
        return [
            'nullable',
            'uuid',
            Rule::exists('idea_stages', 'id')->where('workspace_id', $workspace->id),
        ];
    }
}
