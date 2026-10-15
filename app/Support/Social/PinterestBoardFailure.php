<?php

declare(strict_types=1);

namespace App\Support\Social;

use App\Exceptions\Social\ErrorCategory;
use App\Exceptions\Social\PinterestPublishException;
use App\Exceptions\TokenExpiredException;
use Illuminate\Http\Client\ConnectionException;

final class PinterestBoardFailure
{
    /**
     * The message the web, API and MCP show when reading or creating Pinterest boards fails.
     */
    public static function message(TokenExpiredException|PinterestPublishException|ConnectionException $exception): string
    {
        return __(match (true) {
            $exception instanceof TokenExpiredException => 'posts.form.pinterest.boards_reconnect',
            $exception instanceof PinterestPublishException && $exception->category === ErrorCategory::Permission => 'posts.form.pinterest.boards_reconnect',
            $exception instanceof PinterestPublishException && $exception->category === ErrorCategory::RateLimit => 'posts.form.pinterest.boards_rate_limited',
            default => 'posts.form.pinterest.boards_failed',
        });
    }
}
