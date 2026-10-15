<?php

declare(strict_types=1);

namespace App\Support\Analytics;

/**
 * The Google scopes YouTube analytics reads need.
 *
 * @see https://developers.google.com/youtube/v3/guides/auth/server-side-web-apps#identify-access-scopes
 * @see https://developers.google.com/youtube/analytics/reference/reports/query
 */
final class YouTubeScopes
{
    /**
     * Any one of these lets the Data API read the channel, its uploads and its videos.
     *
     * @var list<string>
     */
    public const array READ = [
        'https://www.googleapis.com/auth/youtube.readonly',
        'https://www.googleapis.com/auth/youtube',
        'https://www.googleapis.com/auth/youtube.force-ssl',
    ];

    /**
     * Any one of these lets the Analytics API run a channel report.
     *
     * @var list<string>
     */
    public const array ANALYTICS = [
        'https://www.googleapis.com/auth/yt-analytics.readonly',
        'https://www.googleapis.com/auth/yt-analytics-monetary.readonly',
    ];
}
