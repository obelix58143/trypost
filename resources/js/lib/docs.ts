import { Platform } from '@/types/platform';

export const DOCS_URL = 'https://docs.trypost.it';

// Anchors of the per-network sections in the media knowledge-base page. The
// two Instagram and two LinkedIn variants share one section each.
const MEDIA_LIMITS_ANCHOR: Record<string, string> = {
    [Platform.Instagram]: 'instagram',
    [Platform.InstagramFacebook]: 'instagram',
    [Platform.Facebook]: 'facebook',
    [Platform.Threads]: 'threads',
    [Platform.X]: 'x-twitter',
    [Platform.LinkedIn]: 'linkedin',
    [Platform.LinkedInPage]: 'linkedin',
    [Platform.TikTok]: 'tiktok',
    [Platform.YouTube]: 'youtube',
    [Platform.Pinterest]: 'pinterest',
    [Platform.Bluesky]: 'bluesky',
    [Platform.Mastodon]: 'mastodon',
    [Platform.Discord]: 'discord',
    [Platform.Telegram]: 'telegram',
};

export const mediaLimitsDocsUrl = (platform: string): string => {
    const anchor = MEDIA_LIMITS_ANCHOR[platform];

    return `${DOCS_URL}/knowledge-base/media${anchor ? `#${anchor}` : ''}`;
};
