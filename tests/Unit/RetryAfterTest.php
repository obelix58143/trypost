<?php

declare(strict_types=1);

use App\Support\Analytics\RetryAfter;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;

test('retry after supports delay seconds and HTTP dates', function () {
    CarbonImmutable::setTestNow('2026-09-23 02:00:00 UTC');

    $seconds = new Response(new PsrResponse(429, ['Retry-After' => ' 7200 ']));
    $date = new Response(new PsrResponse(429, ['Retry-After' => 'Wed, 23 Sep 2026 06:00:00 GMT']));

    expect(RetryAfter::from($seconds)?->toIso8601String())->toBe('2026-09-23T04:00:00+00:00')
        ->and(RetryAfter::from($date)?->toIso8601String())->toBe('2026-09-23T06:00:00+00:00');
});

test('retry after ignores missing and malformed headers', function () {
    $missing = new Response(new PsrResponse(429));
    $malformed = new Response(new PsrResponse(429, ['Retry-After' => 'not a date']));

    expect(RetryAfter::from($missing))->toBeNull()
        ->and(RetryAfter::from($malformed))->toBeNull();
});

test('retry after reads the reset headers networks send without Retry-After', function (array $headers, string $expected) {
    CarbonImmutable::setTestNow('2026-09-23 02:00:00 UTC');

    expect(RetryAfter::from(new Response(new PsrResponse(429, $headers)))?->toIso8601String())->toBe($expected);
})->with([
    'bluesky RateLimit-Reset epoch' => [['RateLimit-Reset' => (string) CarbonImmutable::parse('2026-09-23 02:05:00 UTC')->getTimestamp()], '2026-09-23T02:05:00+00:00'],
    'x x-rate-limit-reset epoch' => [['x-rate-limit-reset' => (string) CarbonImmutable::parse('2026-09-23 02:15:00 UTC')->getTimestamp()], '2026-09-23T02:15:00+00:00'],
]);
