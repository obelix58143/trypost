<?php

declare(strict_types=1);

namespace App\Support\Social;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Throwable;

/**
 * When a network says a limit lifts, read from the refusal itself: the
 * standard Retry-After header, the reset headers X, Bluesky, Mastodon,
 * Pinterest and Discord send, Telegram's and Discord's `retry_after` body
 * field, and Meta's `X-Business-Use-Case-Usage` estimated_time_to_regain_access.
 * The latest future instant wins; null when the network says nothing.
 */
final class NetworkLimitReset
{
    /**
     * A reset value at or above this is a Unix timestamp; below it, seconds from now.
     */
    private const int EPOCH_THRESHOLD = 1_000_000_000;

    /**
     * @var list<string>
     */
    private const array RESET_HEADERS = [
        'Retry-After',
        'x-rate-limit-reset',
        'x-user-limit-24hour-reset',
        'x-app-limit-24hour-reset',
        'ratelimit-reset',
        'x-ratelimit-reset',
    ];

    public static function from(Response $response): ?CarbonInterface
    {
        $candidates = [
            ...array_map(fn (string $header): ?CarbonInterface => self::parse($response->header($header)), self::RESET_HEADERS),
            self::secondsFromNow($response->header('x-ratelimit-reset-after')),
            self::secondsFromNow(data_get(self::json($response), 'retry_after')),
            self::secondsFromNow(data_get(self::json($response), 'parameters.retry_after')),
            self::metaRegainAccess($response->header('x-business-use-case-usage')),
        ];

        $now = now();

        return collect($candidates)
            ->filter(fn (?CarbonInterface $instant): bool => $instant !== null && $instant->greaterThan($now))
            ->sortBy(fn (CarbonInterface $instant): int => $instant->getTimestamp())
            ->last();
    }

    private static function parse(string $value): ?CarbonInterface
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value >= self::EPOCH_THRESHOLD
                ? CarbonImmutable::createFromTimestamp((int) $value)
                : self::secondsFromNow($value);
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private static function secondsFromNow(mixed $seconds): ?CarbonInterface
    {
        if (! is_numeric($seconds) || (float) $seconds <= 0) {
            return null;
        }

        return now()->addSeconds((int) ceil((float) $seconds));
    }

    private static function metaRegainAccess(string $header): ?CarbonInterface
    {
        $usage = json_decode($header, true);

        if (! is_array($usage)) {
            return null;
        }

        $minutes = collect(Arr::flatten(data_get($usage, '*.*.estimated_time_to_regain_access', [])))
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->max();

        return $minutes === null ? null : self::secondsFromNow((float) $minutes * 60);
    }

    /**
     * @return array<string, mixed>
     */
    private static function json(Response $response): array
    {
        $body = rescue(fn (): mixed => $response->json(), null, report: false);

        return is_array($body) ? $body : [];
    }
}
