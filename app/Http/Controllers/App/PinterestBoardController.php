<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\SocialAccount\CreatePinterestBoard;
use App\Actions\SocialAccount\ListPinterestBoards;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Exceptions\Social\PinterestPublishException;
use App\Exceptions\TokenExpiredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Pinterest\StorePinterestBoardRequest;
use App\Http\Resources\App\PinterestBoardResource;
use App\Http\Resources\App\PinterestBoardsResource;
use App\Models\SocialAccount;
use App\Support\Social\PinterestBoardFailure;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PinterestBoardController extends Controller
{
    public function index(Request $request, SocialAccount $account): PinterestBoardsResource
    {
        $this->authorizePinterestAccount($request, $account);

        return new PinterestBoardsResource(
            $this->callPinterest('boards', fn (): array => ListPinterestBoards::refresh($account)),
        );
    }

    public function store(StorePinterestBoardRequest $request, SocialAccount $account): JsonResponse
    {
        $this->authorizePinterestAccount($request, $account);

        $board = $this->callPinterest('name', fn (): array => CreatePinterestBoard::execute($account, $request->validated()));

        return (new PinterestBoardResource($board))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    private function authorizePinterestAccount(Request $request, SocialAccount $account): void
    {
        $this->authorize('view', $account);

        abort_unless($account->platform === SocialPlatform::Pinterest, Response::HTTP_NOT_FOUND);

        $this->authorize('createPost', $request->user()->currentWorkspace);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function callPinterest(string $field, Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (TokenExpiredException|PinterestPublishException|ConnectionException $e) {
            throw ValidationException::withMessages([$field => PinterestBoardFailure::message($e)]);
        }
    }
}
