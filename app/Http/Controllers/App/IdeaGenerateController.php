<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Idea\GenerateIdea;
use App\Http\Requests\App\Idea\GenerateIdeaRequest;
use App\Http\Resources\App\GeneratedIdeaResource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Exceptions\AiException;
use Symfony\Component\HttpFoundation\Response;

class IdeaGenerateController extends Controller
{
    public function __invoke(GenerateIdeaRequest $request): GeneratedIdeaResource|JsonResponse
    {
        $gate = Gate::inspect('useAi', $request->user()->currentWorkspace->account);
        if ($gate->denied()) {
            return response()->json(['message' => $gate->message()], Response::HTTP_PAYMENT_REQUIRED);
        }

        try {
            $idea = GenerateIdea::execute(
                user: $request->user(),
                business: $request->validated('business'),
                audience: $request->validated('audience'),
                notes: $request->validated('notes'),
            );
        } catch (AiException|RequestException|ConnectionException) {
            $idea = null;
        }

        if ($idea === null) {
            return response()->json(['message' => __('create.ideas.errors.generate_failed')], Response::HTTP_BAD_GATEWAY);
        }

        return new GeneratedIdeaResource($idea);
    }
}
