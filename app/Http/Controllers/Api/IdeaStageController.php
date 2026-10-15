<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Idea\CreateIdeaStage;
use App\Actions\Idea\DeleteIdeaStage;
use App\Actions\Idea\ReorderIdeaStages;
use App\Actions\Idea\UpdateIdeaStage;
use App\Http\Requests\Api\IdeaStage\ReorderIdeaStageRequest;
use App\Http\Requests\Api\IdeaStage\StoreIdeaStageRequest;
use App\Http\Requests\Api\IdeaStage\UpdateIdeaStageRequest;
use App\Http\Resources\Api\IdeaStageResource;
use App\Models\Idea;
use App\Models\IdeaStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class IdeaStageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Idea::class);

        $stages = $request->user()->currentWorkspace->ideaStages()->withCount('ideas')->get();

        return IdeaStageResource::collection($stages);
    }

    public function store(StoreIdeaStageRequest $request): JsonResponse
    {
        $stage = CreateIdeaStage::execute($request->user()->currentWorkspace, $request->validated());

        return (new IdeaStageResource($stage))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function reorder(ReorderIdeaStageRequest $request): AnonymousResourceCollection
    {
        $workspace = $request->user()->currentWorkspace;

        ReorderIdeaStages::execute($workspace, data_get($request->validated(), 'stage_ids'));

        return IdeaStageResource::collection($workspace->ideaStages()->withCount('ideas')->get());
    }

    public function update(UpdateIdeaStageRequest $request, IdeaStage $ideaStage): IdeaStageResource
    {
        return new IdeaStageResource(UpdateIdeaStage::execute($ideaStage, $request->validated()));
    }

    public function destroy(IdeaStage $ideaStage): JsonResponse
    {
        $this->authorize('delete', $ideaStage);

        DeleteIdeaStage::execute($ideaStage);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
