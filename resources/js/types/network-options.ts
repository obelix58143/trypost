export const YouTubePrivacyStatus = {
    Public: 'public',
    Unlisted: 'unlisted',
    Private: 'private',
} as const;

export const YouTubeLicense = {
    YouTube: 'youtube',
    CreativeCommon: 'creativeCommon',
} as const;

export const YOUTUBE_TITLE_MAX = 100;
export const THREADS_TOPIC_TAG_MAX = 50;

export const THREAD_MAX_REPLIES = 24;
/** The content type a thread reply publishes as, per network that chains replies. */
export const THREAD_REPLY_CONTENT_TYPES: Readonly<Record<string, string>> = {
    bluesky: 'bluesky_post',
    mastodon: 'mastodon_post',
    x: 'x_post',
};
export const THREAD_PLATFORMS: readonly string[] = Object.keys(
    THREAD_REPLY_CONTENT_TYPES,
);
