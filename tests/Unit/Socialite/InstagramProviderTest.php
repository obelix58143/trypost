<?php

declare(strict_types=1);

use App\Socialite\InstagramProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Request;

test('instagram provider has correct scopes', function () {
    $request = Request::create('/');
    $provider = new InstagramProvider($request, 'client-id', 'client-secret', 'https://example.com/callback');

    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('scopes');
    $property->setAccessible(true);

    expect($property->getValue($provider))->toContain('instagram_business_basic');
    expect($property->getValue($provider))->toContain('instagram_business_content_publish');
});

test('instagram provider has correct token url', function () {
    $request = Request::create('/');
    $provider = new InstagramProvider($request, 'client-id', 'client-secret', 'https://example.com/callback');

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getTokenUrl');
    $method->setAccessible(true);

    expect($method->invoke($provider))->toBe(config('trypost.platforms.instagram.oauth_api').'/oauth/access_token');
});

test('instagram provider generates correct token fields', function () {
    $request = Request::create('/');
    $provider = new InstagramProvider($request, 'client-id', 'client-secret', 'https://example.com/callback');

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getTokenFields');
    $method->setAccessible(true);

    $fields = $method->invoke($provider, 'test-code');

    expect($fields['client_id'])->toBe('client-id');
    expect($fields['client_secret'])->toBe('client-secret');
    expect($fields['grant_type'])->toBe('authorization_code');
    expect($fields['redirect_uri'])->toBe('https://example.com/callback');
    expect($fields['code'])->toBe('test-code');
});

test('instagram provider maps user to object correctly', function () {
    $request = Request::create('/');
    $provider = new InstagramProvider($request, 'client-id', 'client-secret', 'https://example.com/callback');

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('mapUserToObject');
    $method->setAccessible(true);

    $user = $method->invoke($provider, [
        'id' => '12345',
        'username' => 'testuser',
        'name' => 'Test User',
        'profile_picture_url' => 'https://example.com/avatar.jpg',
    ]);

    expect($user->getId())->toBe('12345');
    expect($user->getNickname())->toBe('testuser');
    expect($user->getName())->toBe('Test User');
    expect($user->getAvatar())->toBe('https://example.com/avatar.jpg');
});

test('instagram provider maps user without name uses username', function () {
    $request = Request::create('/');
    $provider = new InstagramProvider($request, 'client-id', 'client-secret', 'https://example.com/callback');

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('mapUserToObject');
    $method->setAccessible(true);

    $user = $method->invoke($provider, [
        'id' => '12345',
        'username' => 'testuser',
    ]);

    expect($user->getName())->toBe('testuser');
});

test('instagram provider reports the permissions the login granted as its scopes', function (array $tokenResponse) {
    $provider = new InstagramProvider(Request::create('/'), 'client-id', 'client-secret', 'https://example.com/callback');
    $provider->setHttpClient(new Client(['handler' => HandlerStack::create(new MockHandler([
        new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode($tokenResponse)),
        new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'long-token', 'expires_in' => 5183944])),
    ]))]));

    $response = $provider->getAccessTokenResponse('code-1');

    expect($response)
        ->access_token->toBe('long-token')
        ->scope->toBe('instagram_business_basic,instagram_business_content_publish');
})->with([
    'documented shape' => [['data' => [['access_token' => 'short-token', 'user_id' => '1', 'permissions' => 'instagram_business_basic,instagram_business_content_publish']]]],
    'flat shape' => [['access_token' => 'short-token', 'user_id' => '1', 'permissions' => ['instagram_business_basic', 'instagram_business_content_publish']]],
]);
