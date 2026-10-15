<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\SocialAccount\GetInstagramAudio;
use App\Actions\SocialAccount\SearchInstagramAudio;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Instagram\IndexInstagramAudioRequest;
use App\Http\Resources\App\InstagramAudioResource;
use App\Models\SocialAccount;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class InstagramAudioController extends Controller
{
    public function index(IndexInstagramAudioRequest $request, SocialAccount $account): AnonymousResourceCollection
    {
        $this->authorizeAudioAccount($request, $account);

        return InstagramAudioResource::collection($this->callInstagram(
            fn (): array => SearchInstagramAudio::execute($account, $request->validated('q'), $request->validated('type')),
        ));
    }

    public function show(Request $request, SocialAccount $account, string $audioId): InstagramAudioResource
    {
        $this->authorizeAudioAccount($request, $account);

        return new InstagramAudioResource($this->callInstagram(
            fn (): array => GetInstagramAudio::execute($account, $audioId),
        ));
    }

    private function authorizeAudioAccount(Request $request, SocialAccount $account): void
    {
        $this->authorize('view', $account);

        abort_unless(in_array($account->platform, [SocialPlatform::Instagram, SocialPlatform::InstagramFacebook], true), Response::HTTP_NOT_FOUND);

        $this->authorize('createPost', $request->user()->currentWorkspace);

        if ($account->platform !== SocialPlatform::InstagramFacebook) {
            throw ValidationException::withMessages(['q' => __('posts.form.instagram.music.requires_facebook')]);
        }
    }

    /**
     * @param  Closure(): array<int|string, mixed>  $callback
     * @return array<int|string, mixed>
     */
    private function callInstagram(Closure $callback): array
    {
        try {
            return $callback();
        } catch (ConnectionException|RequestException) {
            throw ValidationException::withMessages(['q' => __('posts.form.instagram.music.search_failed')]);
        }
    }
}
