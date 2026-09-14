<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Http\Controllers\Auth\OidcController;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use App\Socialite\OidcProvider;
use App\Support\Auth\LoginMethods;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;

beforeEach(function () {
    config()->set('trypost.self_hosted', false);
    config()->set('trypost.oidc_auth_enabled', true);
    config()->set('trypost.oidc_allowed_groups', '');
    config()->set('trypost.oidc_auto_join_enabled', false);
});

/**
 * Stands in for the real provider so the tests never leave the process.
 *
 * @param  array<string, mixed>  $claims  What the provider reports about the user.
 */
function fakeOidcDriver(array $claims = [], ?string $idToken = 'id-token-value'): MockInterface
{
    $claims = array_merge([
        'sub' => 'provider-subject-1',
        'email' => 'member@example.com',
        'name' => 'Example Member',
    ], $claims);

    $socialiteUser = (new SocialiteUser)->setRaw($claims)->map([
        'id' => $claims['sub'],
        'name' => $claims['name'] ?? null,
        'email' => $claims['email'] ?? null,
        'nickname' => $claims['preferred_username'] ?? null,
    ]);

    $driver = Mockery::mock(OidcProvider::class);
    $driver->shouldReceive('user')->andReturn($socialiteUser);
    $driver->shouldReceive('idToken')->andReturn($idToken);
    $driver->shouldReceive('endSessionEndpoint')->andReturn(null)->byDefault();

    Socialite::shouldReceive('driver')->with('oidc')->andReturn($driver);

    return $driver;
}

// ---------------------------------------------------------------- wiring ---

test('login page loads whether oidc is enabled or not', function (bool $enabled) {
    config(['trypost.oidc_auth_enabled' => $enabled]);

    $this->get(route('login'))->assertOk();
})->with([true, false]);

test('login page shares the oidc flag and the configured display name', function () {
    config([
        'trypost.oidc_auth_enabled' => true,
        'trypost.oidc_display_name' => 'Acme SSO',
    ]);

    $response = $this->get(route('login'));

    $props = $response->original->getData()['page']['props'];

    expect($props['oidcAuthEnabled'])->toBeTrue()
        ->and($props['oidcDisplayName'])->toBe('Acme SSO');
});

test('login page reports oidc as disabled when it is off', function () {
    config(['trypost.oidc_auth_enabled' => false]);

    $props = $this->get(route('login'))->original->getData()['page']['props'];

    expect($props['oidcAuthEnabled'])->toBeFalse();
});

test('the redirect route sends the browser to the provider', function () {
    $driver = Mockery::mock(OidcProvider::class);
    $driver->shouldReceive('redirect')->andReturn(redirect('https://idp.example.com/authorize'));
    Socialite::shouldReceive('driver')->with('oidc')->andReturn($driver);

    $this->get(route('auth.oidc.redirect'))
        ->assertRedirect('https://idp.example.com/authorize');
});

test('both oidc routes 404 while the feature is disabled', function (string $route) {
    config(['trypost.oidc_auth_enabled' => false]);

    $this->get(route($route))->assertNotFound();
})->with(['auth.oidc.redirect', 'auth.oidc.callback']);

test('a failing provider sends the user back to the login page', function () {
    $driver = Mockery::mock(OidcProvider::class);
    $driver->shouldReceive('user')->andThrow(new RuntimeException('token rejected'));
    Socialite::shouldReceive('driver')->with('oidc')->andReturn($driver);

    $this->get(route('auth.oidc.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

// ----------------------------------------------------------- signing in ---

test('a returning user is matched by the provider subject', function () {
    $user = User::factory()->create([
        'oidc_id' => 'provider-subject-1',
        'email' => 'someone-else@example.com',
    ]);

    fakeOidcDriver(['sub' => 'provider-subject-1', 'email' => 'member@example.com']);

    $this->get(route('auth.oidc.callback'))->assertRedirect(route('app.home'));

    $this->assertAuthenticatedAs($user);
});

test('an existing local account is linked by email and keeps the subject', function () {
    $user = User::factory()->create(['email' => 'member@example.com', 'oidc_id' => null]);

    fakeOidcDriver();

    $this->get(route('auth.oidc.callback'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->oidc_id)->toBe('provider-subject-1');
});

test('the id token is kept for logout', function () {
    User::factory()->create(['email' => 'member@example.com']);

    fakeOidcDriver();

    $this->get(route('auth.oidc.callback'));

    expect(session(OidcController::ID_TOKEN_SESSION_KEY))->toBe('id-token-value');
});

test('signing in gets a fresh session id', function () {
    User::factory()->create(['email' => 'member@example.com']);

    fakeOidcDriver();

    $this->startSession();
    $before = session()->getId();

    $this->get(route('auth.oidc.callback'));

    expect(session()->getId())->not->toBe($before);
});

// -------------------------------------------------------------- security ---

test('an unverified email cannot claim an existing account', function () {
    $user = User::factory()->create(['email' => 'member@example.com', 'oidc_id' => null]);

    fakeOidcDriver(['email_verified' => false]);

    $this->get(route('auth.oidc.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($user->fresh()->oidc_id)->toBeNull();
});

test('a verified email is accepted', function () {
    $user = User::factory()->create(['email' => 'member@example.com']);

    fakeOidcDriver(['email_verified' => true]);

    $this->get(route('auth.oidc.callback'));

    $this->assertAuthenticatedAs($user);
});

test('an unverified email still signs in when it collides with nothing', function () {
    // Providers that simply do not do email verification report false for
    // everyone. That must not lock them out - it only rules out taking over
    // an account that already exists.
    config([
        'trypost.self_hosted' => false,
        'trypost.oidc_auto_join_enabled' => false,
    ]);

    fakeOidcDriver(['email' => 'newcomer@example.com', 'email_verified' => false]);

    $this->get(route('auth.oidc.callback'));

    $this->assertAuthenticated();
    expect(User::where('email', 'newcomer@example.com')->exists())->toBeTrue();
});

test('the subject matches even when the email is unverified', function () {
    $user = User::factory()->create([
        'oidc_id' => 'provider-subject-1',
        'email' => 'member@example.com',
    ]);

    fakeOidcDriver(['email_verified' => false]);

    $this->get(route('auth.oidc.callback'));

    $this->assertAuthenticatedAs($user);
});

test('a provider that returns no email is refused', function () {
    fakeOidcDriver(['email' => null]);

    $this->get(route('auth.oidc.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('users outside the allowed groups are turned away', function () {
    User::factory()->create(['email' => 'member@example.com']);
    config(['trypost.oidc_allowed_groups' => 'marketing, board']);

    fakeOidcDriver(['groups' => ['support']]);

    $this->get(route('auth.oidc.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a user in one of the allowed groups gets in', function () {
    $user = User::factory()->create(['email' => 'member@example.com']);
    config(['trypost.oidc_allowed_groups' => 'marketing, board']);

    fakeOidcDriver(['groups' => ['support', 'board']]);

    $this->get(route('auth.oidc.callback'));

    $this->assertAuthenticatedAs($user);
});

test('without an allow list the provider alone decides', function () {
    $user = User::factory()->create(['email' => 'member@example.com']);
    config(['trypost.oidc_allowed_groups' => '']);

    fakeOidcDriver(['groups' => []]);

    $this->get(route('auth.oidc.callback'));

    $this->assertAuthenticatedAs($user);
});

// ------------------------------------------------------------ onboarding ---

test('self-hosted still requires an invite while auto-join is off', function () {
    config([
        'trypost.self_hosted' => true,
        'trypost.oidc_auto_join_enabled' => false,
    ]);

    fakeOidcDriver();

    $this->get(route('auth.oidc.callback'))->assertNotFound();

    $this->assertGuest();
});

test('auto-join places a new user on the shared account', function () {
    config([
        'trypost.self_hosted' => true,
        'trypost.oidc_auto_join_enabled' => true,
        'trypost.oidc_auto_join_role' => Role::Member->value,
    ]);

    $account = Account::factory()->create(['created_at' => now()->subDay()]);
    $owner = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $owner->id]);
    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);

    fakeOidcDriver(['email' => 'newcomer@example.com', 'sub' => 'subject-new']);

    $this->get(route('auth.oidc.callback'))->assertRedirect(route('app.home'));

    $user = User::where('email', 'newcomer@example.com')->firstOrFail();

    expect($user->account_id)->toBe($account->id)
        ->and($user->current_workspace_id)->toBe($workspace->id)
        ->and($workspace->members()->where('users.id', $user->id)->exists())->toBeTrue();
});

test('auto-join stays off unless the instance is self-hosted', function () {
    config([
        'trypost.self_hosted' => false,
        'trypost.oidc_auto_join_enabled' => true,
    ]);

    $account = Account::factory()->create(['created_at' => now()->subDay()]);

    fakeOidcDriver(['email' => 'newcomer@example.com', 'sub' => 'subject-new']);

    $this->get(route('auth.oidc.callback'));

    $user = User::where('email', 'newcomer@example.com')->firstOrFail();

    expect($user->account_id)->not->toBe($account->id);
});

// ---------------------------------------------------------------- logout ---

test('logging out also ends the session at the provider', function () {
    $user = User::factory()->create(['oidc_id' => 'provider-subject-1']);

    $driver = Mockery::mock(OidcProvider::class);
    $driver->shouldReceive('endSessionEndpoint')->andReturn('https://idp.example.com/logout');
    Socialite::shouldReceive('driver')->with('oidc')->andReturn($driver);

    $response = $this->actingAs($user)
        ->withSession([OidcController::ID_TOKEN_SESSION_KEY => 'id-token-value'])
        ->post(route('logout'));

    $response->assertRedirectContains('https://idp.example.com/logout');
    $response->assertRedirectContains('id_token_hint=id-token-value');

    $this->assertGuest();
});

test('no post-logout redirect is sent unless one is configured', function () {
    // Providers reject the whole logout when the URI is not registered with
    // them character for character, which would leave the user signed in while
    // believing they are not.
    config(['trypost.oidc_post_logout_redirect_uri' => null]);

    $user = User::factory()->create(['oidc_id' => 'provider-subject-1']);

    $driver = Mockery::mock(OidcProvider::class);
    $driver->shouldReceive('endSessionEndpoint')->andReturn('https://idp.example.com/logout');
    Socialite::shouldReceive('driver')->with('oidc')->andReturn($driver);

    $response = $this->actingAs($user)
        ->withSession([OidcController::ID_TOKEN_SESSION_KEY => 'id-token-value'])
        ->post(route('logout'));

    expect($response->headers->get('Location'))->not->toContain('post_logout_redirect_uri');
});

test('a configured post-logout redirect is passed through untouched', function () {
    config(['trypost.oidc_post_logout_redirect_uri' => 'https://app.example.com/']);

    $user = User::factory()->create(['oidc_id' => 'provider-subject-1']);

    $driver = Mockery::mock(OidcProvider::class);
    $driver->shouldReceive('endSessionEndpoint')->andReturn('https://idp.example.com/logout');
    Socialite::shouldReceive('driver')->with('oidc')->andReturn($driver);

    $response = $this->actingAs($user)
        ->withSession([OidcController::ID_TOKEN_SESSION_KEY => 'id-token-value'])
        ->post(route('logout'));

    expect($response->headers->get('Location'))
        ->toContain('post_logout_redirect_uri='.urlencode('https://app.example.com/'));
});

test('logout stays local when the user did not use oidc', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');

    $this->assertGuest();
});

test('logout stays local when provider logout is switched off', function () {
    config(['trypost.oidc_logout_enabled' => false]);

    $user = User::factory()->create(['oidc_id' => 'provider-subject-1']);

    $this->actingAs($user)
        ->withSession([OidcController::ID_TOKEN_SESSION_KEY => 'id-token-value'])
        ->post(route('logout'))
        ->assertRedirect('/');
});

// -------------------------------------------------------------- settings ---

test('the settings page can start an oidc connect', function () {
    $user = User::factory()->create();

    $driver = Mockery::mock(OidcProvider::class);
    $driver->shouldReceive('redirect')->andReturn(redirect('https://idp.example.com/authorize'));
    Socialite::shouldReceive('driver')->with('oidc')->andReturn($driver);

    $this->actingAs($user)
        ->get(route('app.authentication.connect-provider', 'oidc'))
        ->assertRedirect('https://idp.example.com/authorize');
});

test('the settings page lists oidc among the providers', function () {
    $user = User::factory()->create();

    $props = $this->actingAs($user)
        ->get(route('app.authentication.edit'))
        ->original->getData()['page']['props'];

    expect(collect($props['connectedAccounts'])->pluck('provider'))->toContain('oidc');
});

// ------------------------------------------------------------ key caching ---

/**
 * Builds a throwaway JWKS plus a Guzzle client that serves it, so the key
 * handling can be exercised without touching the network.
 *
 * @return array{0: Client, 1: array<string, mixed>}
 */
function fakeJwksClient(): array
{
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    $details = openssl_pkey_get_details($key);

    $base64url = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    $jwks = ['keys' => [[
        'kty' => 'RSA',
        'kid' => 'test-key',
        'use' => 'sig',
        'alg' => 'RS256',
        'n' => $base64url($details['rsa']['n']),
        'e' => $base64url($details['rsa']['e']),
    ]]];

    $discovery = [
        'issuer' => 'https://idp.example.com',
        'authorization_endpoint' => 'https://idp.example.com/authorize',
        'token_endpoint' => 'https://idp.example.com/token',
        'userinfo_endpoint' => 'https://idp.example.com/userinfo',
        'jwks_uri' => 'https://idp.example.com/jwks',
    ];

    // Discovery is fetched once and then cached, so every later request in a
    // test is for the key set.
    $handler = new MockHandler([
        new Response(200, [], json_encode($discovery)),
        new Response(200, [], json_encode($jwks)),
        new Response(200, [], json_encode($jwks)),
        new Response(200, [], json_encode($jwks)),
    ]);

    return [new Client(['handler' => HandlerStack::create($handler)]), $jwks];
}

test('signing keys survive a cache store that serializes', function () {
    // The array store keeps objects as they are; a store that serializes would
    // choke on the OpenSSL key objects inside a parsed key set, so the raw
    // document has to be what gets cached.
    config(['cache.default' => 'file']);
    cache()->clear();

    config([
        'services.oidc.discovery_url' => 'https://idp.example.com',
        'services.oidc.client_id' => 'client-id',
    ]);

    [$client] = fakeJwksClient();

    $provider = new OidcProvider(request(), 'client-id', 'client-secret', 'https://app.example.com/auth/oidc/callback');
    $provider->setHttpClient($client);

    $method = new ReflectionMethod($provider, 'signingKeys');
    $method->setAccessible(true);

    $first = $method->invoke($provider);

    // Second call is served from the cache - this is where the old code blew up.
    $second = $method->invoke($provider);

    expect($first)->toBeArray()->not->toBeEmpty()
        ->and($second)->toBeArray()->not->toBeEmpty()
        ->and(array_keys($second))->toBe(array_keys($first));
});

test('a refresh refetches the key set', function () {
    config(['cache.default' => 'file']);
    cache()->clear();

    config(['services.oidc.discovery_url' => 'https://idp.example.com']);

    [$client] = fakeJwksClient();

    $provider = new OidcProvider(request(), 'client-id', 'client-secret', 'https://app.example.com/auth/oidc/callback');
    $provider->setHttpClient($client);

    $method = new ReflectionMethod($provider, 'signingKeys');
    $method->setAccessible(true);

    $method->invoke($provider);

    // Forces a second trip to the provider, which the mock handler answers.
    expect($method->invoke($provider, true))->toBeArray()->not->toBeEmpty();
});

// ------------------------------------------------- password sign-in toggle ---

test('the password routes stay open by default', function (string $route) {
    config(['trypost.password_login_enabled' => true]);

    $this->get(route($route))->assertOk();
})->with(['login', 'password.request']);

test('switching password sign-in off closes its routes', function () {
    config([
        'trypost.password_login_enabled' => false,
        'trypost.oidc_auth_enabled' => true,
    ]);

    // The login page itself stays - it is where the provider buttons live.
    $this->get(route('login'))->assertOk();

    $this->post(route('login.store'), [
        'email' => 'member@example.com',
        'password' => 'Password123!',
    ])->assertNotFound();

    $this->get(route('password.request'))->assertNotFound();
    $this->post(route('password.email'), ['email' => 'member@example.com'])->assertNotFound();
    $this->get(route('password.reset', ['token' => 'whatever']))->assertNotFound();
});

test('a correct password is still refused once sign-in is switched off', function () {
    config([
        'trypost.password_login_enabled' => false,
        'trypost.oidc_auth_enabled' => true,
    ]);

    User::factory()->create([
        'email' => 'member@example.com',
        'password' => 'Password123!',
    ]);

    $this->post(route('login.store'), [
        'email' => 'member@example.com',
        'password' => 'Password123!',
    ])->assertNotFound();

    $this->assertGuest();
});

test('password sign-in cannot be switched off while it is the only way in', function () {
    // Otherwise one environment variable locks every user out of the instance.
    config([
        'trypost.password_login_enabled' => false,
        'trypost.oidc_auth_enabled' => false,
        'trypost.google_auth_enabled' => false,
        'trypost.github_auth_enabled' => false,
    ]);

    expect(LoginMethods::passwordEnabled())->toBeTrue();

    $this->get(route('password.request'))->assertOk();
});

test('the login page says whether the password form belongs there', function (bool $enabled) {
    config([
        'trypost.password_login_enabled' => $enabled,
        'trypost.oidc_auth_enabled' => true,
    ]);

    $props = $this->get(route('login'))->original->getData()['page']['props'];

    expect($props['passwordLoginEnabled'])->toBe($enabled);
})->with([true, false]);

test('registering with a password is closed while password sign-in is off', function () {
    config([
        'trypost.self_hosted' => false,
        'trypost.password_login_enabled' => false,
        'trypost.oidc_auth_enabled' => true,
    ]);

    // The page stays - it carries the provider buttons.
    $this->get(route('register'))->assertOk();

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'nobody@example.com',
        'password' => 'Password123!',
    ])->assertNotFound();

    expect(User::where('email', 'nobody@example.com')->exists())->toBeFalse();
});

test('logging out from the app hands the browser a full page visit', function () {
    // The app posts logout through Inertia. A plain redirect gets followed by
    // fetch(), which dies on the provider's CORS preflight - the browser never
    // navigates and the provider session survives. Inertia answers a 409 with
    // X-Inertia-Location so the client leaves the page properly.
    config(['trypost.oidc_post_logout_redirect_uri' => null]);

    $user = User::factory()->create(['oidc_id' => 'provider-subject-1']);

    $driver = Mockery::mock(OidcProvider::class);
    $driver->shouldReceive('endSessionEndpoint')->andReturn('https://idp.example.com/logout');
    Socialite::shouldReceive('driver')->with('oidc')->andReturn($driver);

    $response = $this->actingAs($user)
        ->withSession([OidcController::ID_TOKEN_SESSION_KEY => 'id-token-value'])
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('logout'));

    $response->assertStatus(409);

    expect($response->headers->get('X-Inertia-Location'))
        ->toContain('https://idp.example.com/logout')
        ->toContain('id_token_hint=id-token-value');

    $this->assertGuest();
});

// ------------------------------------------------- roles from provider groups ---

/**
 * A workspace with one member, so role changes have something to act on.
 *
 * @return array{0: User, 1: Workspace}
 */
function memberInWorkspace(string $email, string $role = 'member'): array
{
    $account = Account::factory()->create(['created_at' => now()->subDay()]);
    $owner = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $owner->id]);
    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);

    $user = User::factory()->create([
        'email' => $email,
        'account_id' => $account->id,
        'oidc_id' => 'provider-subject-1',
    ]);
    $workspace->members()->attach($user->id, ['role' => $role]);

    return [$user, $workspace];
}

test('a member of an admin group becomes workspace admin on sign-in', function () {
    config(['trypost.oidc_admin_groups' => 'board, ops']);

    [$user, $workspace] = memberInWorkspace('member@example.com');

    fakeOidcDriver(['groups' => ['staff', 'ops']]);

    $this->get(route('auth.oidc.callback'));

    expect($workspace->members()->where('users.id', $user->id)->first()->pivot->role)
        ->toBe('admin');
});

test('losing the admin group drops the role again on the next sign-in', function () {
    // This is what makes offboarding work in one place: take someone out of
    // the group at the provider and their rights go with it.
    config([
        'trypost.oidc_admin_groups' => 'board',
        'trypost.oidc_auto_join_role' => 'member',
    ]);

    [$user, $workspace] = memberInWorkspace('member@example.com', 'admin');

    fakeOidcDriver(['groups' => ['staff']]);

    $this->get(route('auth.oidc.callback'));

    expect($workspace->members()->where('users.id', $user->id)->first()->pivot->role)
        ->toBe('member');
});

test('roles are left alone while no admin group is configured', function () {
    config(['trypost.oidc_admin_groups' => '']);

    [$user, $workspace] = memberInWorkspace('member@example.com', 'admin');

    fakeOidcDriver(['groups' => []]);

    $this->get(route('auth.oidc.callback'));

    // Without the setting the application stays in charge of roles.
    expect($workspace->members()->where('users.id', $user->id)->first()->pivot->role)
        ->toBe('admin');
});
