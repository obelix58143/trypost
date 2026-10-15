<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Post\ShowComposerLiveDataRequest;
use App\Http\Resources\App\ComposerLiveDataResource;
use App\Http\Resources\App\HandleInertiaRequests\ComposerResource;

class ComposerLiveDataController extends Controller
{
    public function __invoke(ShowComposerLiveDataRequest $request): ComposerLiveDataResource
    {
        $workspace = $request->user()->currentWorkspace;
        $composerKey = ComposerResource::key($workspace);

        return new ComposerLiveDataResource(
            $request->validated('key') === $composerKey
                ? []
                : ['composer' => ComposerResource::make($workspace), 'composerKey' => $composerKey],
        );
    }
}
