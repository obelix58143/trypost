<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use App\Support\Social\NetworkLimitReset;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

class RetryAfter
{
    /**
     * When the network said its limit lifts: Retry-After or the reset headers
     * each network sends (Bluesky's RateLimit-Reset, X's x-rate-limit-reset, ...).
     */
    public static function from(Response $response): ?CarbonImmutable
    {
        $resetAt = NetworkLimitReset::from($response);

        return $resetAt === null ? null : CarbonImmutable::instance($resetAt)->utc();
    }
}
