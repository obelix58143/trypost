<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SocialAccount\CreatePinterestBoard;
use App\Actions\SocialAccount\ListPinterestBoards;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\Social\ErrorCategory;
use App\Exceptions\Social\PinterestPublishException;
use App\Exceptions\TokenExpiredException;
use App\Http\Requests\Api\SocialAccount\StorePinterestBoardRequest;
use App\Http\Resources\Api\PinterestBoardResource;
use App\Models\SocialAccount;
use App\Support\Social\PinterestBoardFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PinterestBoardController extends Controller
{
    public function index(Request $request, SocialAccount $account): JsonResponse
    {
        $this->authorize('view', $account);

        if ($account->platform !== Platform::Pinterest) {
            return response()->json(
                ['message' => 'Boards are only available for Pinterest accounts.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            return response()->json(ListPinterestBoards::execute($account));
        } catch (TokenExpiredException $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                Response::HTTP_UNAUTHORIZED,
            );
        } catch (PinterestPublishException $e) {
            return response()->json(
                ['message' => $e->userMessage],
                $this->statusForPinterestCategory($e->category),
            );
        }
    }

    public function store(StorePinterestBoardRequest $request, SocialAccount $account): JsonResponse
    {
        try {
            $board = CreatePinterestBoard::execute($account, $request->validated());
        } catch (TokenExpiredException|PinterestPublishException|ConnectionException $e) {
            throw ValidationException::withMessages(['name' => PinterestBoardFailure::message($e)]);
        }

        return (new PinterestBoardResource($board))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    private function statusForPinterestCategory(ErrorCategory $category): int
    {
        return match ($category) {
            ErrorCategory::RateLimit => Response::HTTP_TOO_MANY_REQUESTS,
            ErrorCategory::Permission => Response::HTTP_FORBIDDEN,
            default => Response::HTTP_BAD_GATEWAY,
        };
    }
}
