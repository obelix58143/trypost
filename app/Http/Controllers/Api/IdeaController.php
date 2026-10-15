<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Idea\CreateIdea;
use App\Actions\Idea\DeleteIdeas;
use App\Actions\Idea\DuplicateIdea;
use App\Actions\Idea\ListIdeas;
use App\Actions\Idea\MoveIdea;
use App\Actions\Idea\UpdateIdea;
use App\Http\Requests\Api\Idea\BulkDestroyIdeasRequest;
use App\Http\Requests\Api\Idea\ListIdeasRequest;
use App\Http\Requests\Api\Idea\MoveIdeaRequest;
use App\Http\Requests\Api\Idea\StoreIdeaRequest;
use App\Http\Requests\Api\Idea\UpdateIdeaRequest;
use App\Http\Resources\Api\IdeaResource;
use App\Models\Idea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class IdeaController extends Controller
{
    public function index(ListIdeasRequest $request): AnonymousResourceCollection
    {
        $ideas = ListIdeas::query($request->user()->currentWorkspace, $request->filters())
            ->with('labels')
            ->paginate((int) config('app.pagination.default'));

        return IdeaResource::collection($ideas);
    }

    public function show(Idea $idea): IdeaResource
    {
        $this->authorize('view', $idea);

        return new IdeaResource($idea->load('labels'));
    }

    public function store(StoreIdeaRequest $request): JsonResponse
    {
        $idea = CreateIdea::execute($request->user()->currentWorkspace, $request->user(), $request->validated());

        return (new IdeaResource($idea->load('labels')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateIdeaRequest $request, Idea $idea): IdeaResource
    {
        return new IdeaResource(UpdateIdea::execute($idea, $request->validated())->load('labels'));
    }

    public function destroy(Idea $idea): JsonResponse
    {
        $this->authorize('delete', $idea);

        DeleteIdeas::execute($idea->workspace, [$idea->id]);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function bulkDestroy(BulkDestroyIdeasRequest $request): JsonResponse
    {
        DeleteIdeas::execute($request->user()->currentWorkspace, data_get($request->validated(), 'idea_ids'));

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function duplicate(Request $request, Idea $idea): JsonResponse
    {
        $this->authorize('update', $idea);

        $copy = DuplicateIdea::execute($idea, $request->user());

        return (new IdeaResource($copy->load('labels')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function move(MoveIdeaRequest $request, Idea $idea): IdeaResource
    {
        MoveIdea::execute(
            $idea,
            data_get($request->validated(), 'idea_stage_id'),
            data_get($request->validated(), 'idea_ids'),
        );

        return new IdeaResource($idea->fresh()->load('labels'));
    }
}
