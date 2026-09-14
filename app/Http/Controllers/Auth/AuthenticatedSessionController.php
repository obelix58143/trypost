<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Auth\SocialAuthProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\LoginRequest;
use App\Jobs\PostHog\SyncUser;
use App\Models\Invite;
use App\Socialite\OidcProvider;
use App\Services\PostHogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'status' => session('status'),
            'email' => $request->query('email'),
            'invite' => $request->query('invite'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if ($locale = $request->validated('locale')) {
            $user = $request->user();
            $user->update(['locale' => $locale]);

            if (PostHogService::shouldTrack()) {
                SyncUser::dispatch((string) $user->id);
            }
        }

        if ($invite = Invite::fromId($request->string('invite')->toString())) {
            return redirect()->route('app.invites.show', $invite);
        }

        return redirect()->intended(route('app.calendar'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Read before the session goes away: RP-initiated logout needs the ID
        // token of this session as a hint for the provider.
        $endSessionUrl = $this->oidcEndSessionUrl($request);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return $endSessionUrl ? redirect()->away($endSessionUrl) : redirect('/');
    }

    /**
     * Where to send the browser so the identity provider ends its own session
     * as well. Without this, "log out" only clears the local session and the
     * next click signs the same user straight back in - which is the opposite
     * of what someone on a shared machine expects.
     *
     * Null when the user did not sign in through OIDC, when the provider
     * publishes no logout endpoint, or when the operator turned it off.
     */
    private function oidcEndSessionUrl(Request $request): ?string
    {
        if (! SocialAuthProvider::Oidc->isEnabled() || ! config('trypost.oidc_logout_enabled')) {
            return null;
        }

        $idToken = $request->session()->get(OidcController::ID_TOKEN_SESSION_KEY);

        if (! is_string($idToken) || blank($idToken)) {
            return null;
        }

        try {
            $driver = Socialite::driver('oidc');
        } catch (\Throwable) {
            return null;
        }

        if (! $driver instanceof OidcProvider || blank($endpoint = $driver->endSessionEndpoint())) {
            return null;
        }

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').http_build_query([
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => url('/'),
            'client_id' => config('services.oidc.client_id'),
        ]);
    }
}
