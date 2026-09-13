import type { Page } from '@inertiajs/core';

/** CamelCase shape consumed by the Vue media picker / compliance checks. */
export type MediaRules = {
    maxFiles: number;
    minFiles?: number;
    acceptImages: boolean;
    acceptVideos: boolean;
    acceptDocuments?: boolean;
    requiresMedia: boolean;
    acceptsGif: boolean;
    acceptsMov: boolean;
    forbidsMixedMedia?: boolean;
    maxImageBytes?: number;
    maxVideoBytes?: number;
    maxDocumentBytes?: number;
    maxVideoDurationSec?: number;
    aspectRatioMin?: number;
    aspectRatioMax?: number;
    autoFitsImage?: boolean;
};

/**
 * Snake_case payload from ContentType::mediaRules() / mediaRulesForFrontend().
 */
export type ContentTypeMediaRule = {
    max_files: number;
    min_files: number | null;
    accept_images: boolean;
    accept_videos: boolean;
    accept_documents: boolean;
    requires_media: boolean;
    accepts_gif: boolean;
    accepts_mov: boolean;
    forbids_mixed_media: boolean;
    max_image_bytes: number | null;
    max_video_bytes: number | null;
    max_document_bytes: number | null;
    max_video_duration_sec: number | null;
    aspect_ratio_min: number | null;
    aspect_ratio_max: number | null;
    auto_fits_image: boolean;
};

type ContentTypeMediaRulesMap = Record<string, ContentTypeMediaRule>;

let cachedRules: ContentTypeMediaRulesMap | null = null;

export const syncContentTypeMediaRules = (page: Page): void => {
    const rules = page.props.contentTypeMediaRules as ContentTypeMediaRulesMap | undefined;

    if (rules) {
        cachedRules = rules;
    }
};

export const mediaRuleFor = (contentType: string): ContentTypeMediaRule | undefined => {
    return cachedRules?.[contentType];
};

export const toMediaRules = (rule: ContentTypeMediaRule): MediaRules => ({
    maxFiles: rule.max_files,
    ...(rule.min_files !== null && { minFiles: rule.min_files }),
    acceptImages: rule.accept_images,
    acceptVideos: rule.accept_videos,
    ...(rule.accept_documents && { acceptDocuments: true }),
    requiresMedia: rule.requires_media,
    acceptsGif: rule.accepts_gif,
    acceptsMov: rule.accepts_mov,
    ...(rule.forbids_mixed_media && { forbidsMixedMedia: true }),
    ...(rule.max_image_bytes !== null && { maxImageBytes: rule.max_image_bytes }),
    ...(rule.max_video_bytes !== null && { maxVideoBytes: rule.max_video_bytes }),
    ...(rule.max_document_bytes !== null && { maxDocumentBytes: rule.max_document_bytes }),
    ...(rule.max_video_duration_sec !== null && { maxVideoDurationSec: rule.max_video_duration_sec }),
    ...(rule.aspect_ratio_min !== null && { aspectRatioMin: rule.aspect_ratio_min }),
    ...(rule.aspect_ratio_max !== null && { aspectRatioMax: rule.aspect_ratio_max }),
    ...(rule.auto_fits_image && { autoFitsImage: true }),
});
