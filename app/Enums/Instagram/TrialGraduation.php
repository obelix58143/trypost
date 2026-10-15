<?php

declare(strict_types=1);

namespace App\Enums\Instagram;

/**
 * `trial_params.graduation_strategy` of a trial reel: shared only with
 * non-followers until it graduates to everyone.
 *
 * @see https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user/media
 */
enum TrialGraduation: string
{
    /** Graduated by hand in the Instagram app. */
    case Manual = 'MANUAL';

    /** Graduated by Instagram when the reel performs well. */
    case Performance = 'SS_PERFORMANCE';
}
