<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\SocialAccountResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SocialAccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = $request->user()->currentWorkspace->socialAccounts()->paginate((int) config('app.pagination.default'));

        return SocialAccountResource::collection($accounts);
    }
}
