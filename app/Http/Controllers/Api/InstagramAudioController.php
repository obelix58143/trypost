<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SocialAccount\GetInstagramAudio;
use App\Actions\SocialAccount\SearchInstagramAudio;
use App\Enums\SocialAccount\Platform;
use App\Http\Requests\Api\SocialAccount\IndexInstagramAudioRequest;
use App\Http\Resources\Api\InstagramAudioResource;
use App\Models\SocialAccount;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class InstagramAudioController extends Controller
{
    public function index(IndexInstagramAudioRequest $request, SocialAccount $account): JsonResponse|AnonymousResourceCollection
    {
        return $this->lookup($account, fn (): AnonymousResourceCollection => InstagramAudioResource::collection(
            SearchInstagramAudio::execute($account, $request->validated('q'), $request->validated('type')),
        ));
    }

    public function show(SocialAccount $account, string $audioId): JsonResponse|InstagramAudioResource
    {
        return $this->lookup($account, fn (): InstagramAudioResource => new InstagramAudioResource(GetInstagramAudio::execute($account, $audioId)));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $lookup
     * @return T|JsonResponse
     */
    private function lookup(SocialAccount $account, Closure $lookup): mixed
    {
        $this->authorize('view', $account);
        $this->authorize('createPost', $account->workspace);

        if (! in_array($account->platform, [Platform::Instagram, Platform::InstagramFacebook], true)) {
            return response()->json(
                ['message' => 'Instagram audio is only available for Instagram accounts.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($account->platform !== Platform::InstagramFacebook) {
            return response()->json(
                ['message' => __('posts.form.instagram.music.requires_facebook')],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            return $lookup();
        } catch (ConnectionException|RequestException) {
            return response()->json(
                ['message' => __('posts.form.instagram.music.search_failed')],
                Response::HTTP_BAD_GATEWAY,
            );
        }
    }
}
