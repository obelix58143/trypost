/**
 * Single source of truth for media-type detection on the frontend — the mirror
 * of the backend `App\Enums\Media\Type`. Every "is this an image / video / PDF?"
 * check goes through here so the answer can't drift between components.
 */

export const MediaType = {
    Image: 'image',
    Video: 'video',
    Document: 'document',
} as const;

export type MediaType = (typeof MediaType)[keyof typeof MediaType];

/** MIME allow-list we accept on upload — mirrors Type::allowedMimeTypes(). */
export const ALLOWED_MIME_TYPES: Record<MediaType, readonly string[]> = {
    [MediaType.Image]: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    [MediaType.Video]: ['video/mp4', 'video/quicktime'],
    [MediaType.Document]: ['application/pdf'],
};

const GIF_MIME = 'image/gif';
const MOV_MIME = 'video/quicktime';
const PDF_MIME = 'application/pdf';

const MEDIA_TYPES = Object.values(MediaType);

// Broader than the upload allow-list so already-stored files in legacy formats
// still resolve. The backend (Type::fromExtension) consults the MIME registry;
// the browser has none, so this is the subset of formats we have seen stored.
const CLASSIFIABLE_EXTENSIONS: Record<MediaType, readonly string[]> = {
    [MediaType.Image]: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'heic', 'heif'],
    [MediaType.Video]: ['mp4', 'mov', 'avi', 'wmv', 'webm', 'mkv', 'm4v'],
    [MediaType.Document]: ['pdf'],
};

/**
 * The file name of a bare name, a storage key or a full URL. The native URL
 * parser owns the slicing — directories, `?query` and `#hash` are its job.
 * Relative input resolves against the page, the same way an `<a href>` would.
 */
const fileNameOf = (nameOrPath: string): string => URL.parse(nameOrPath, document.baseURI)?.pathname.split('/').pop() ?? '';

/** Lower-cased extension of the file name, `''` when there is none — `path.extname` for the browser. */
const extensionOf = (nameOrPath: string | null | undefined): string => {
    const fileName = fileNameOf(nameOrPath ?? '');
    const dot = fileName.lastIndexOf('.');

    return dot === -1 ? '' : fileName.slice(dot + 1).toLowerCase();
};

/** Lower-cased `type/subtype` with any `; codecs=…` parameters dropped, so comparisons are exact. */
const normalizeMime = (mime: string | null | undefined): string => (mime ?? '').split(';', 1)[0].trim().toLowerCase();

/** The `type` half of `type/subtype`, `''` when there is no slash to split on. */
const mimeFamily = (mime: string): string => (mime.includes('/') ? mime.split('/', 1)[0] : '');

const ownsMime = (type: MediaType, mime: string): boolean =>
    type === MediaType.Document ? mime === PDF_MIME : mimeFamily(mime) === type;

/** The `accept` attribute value for a file input that takes any media we allow. */
export const acceptAttribute = (): string => Object.values(ALLOWED_MIME_TYPES).flat().join(',');

/** The structural shape every classifiable media item satisfies. */
interface ClassifiableMedia {
    type?: string | null;
    mime_type?: string | null;
    original_filename?: string | null;
    path?: string | null;
}

/** Resolve a MediaType from a raw MIME string (e.g. a browser `File.type`). */
export const fromMimeType = (mime: string | null | undefined): MediaType | null => {
    const normalized = normalizeMime(mime);

    return MEDIA_TYPES.find((type) => ownsMime(type, normalized)) ?? null;
};

/** Resolve a MediaType from a filename or path extension. */
export const fromExtension = (nameOrPath: string | null | undefined): MediaType | null => {
    const ext = extensionOf(nameOrPath);

    return ext === '' ? null : MEDIA_TYPES.find((type) => CLASSIFIABLE_EXTENSIONS[type].includes(ext)) ?? null;
};

/**
 * Classify a media item. Trusts the server-assigned `type` first, then the MIME,
 * then falls back to the filename extension so already-stored items still
 * resolve. Returns null only when nothing identifies the item.
 */
export const classify = (item: ClassifiableMedia | null | undefined): MediaType | null => {
    if (! item) return null;

    const explicit = item.type;
    if (explicit === MediaType.Image || explicit === MediaType.Video || explicit === MediaType.Document) {
        return explicit;
    }

    return fromMimeType(item.mime_type) ?? fromExtension(item.original_filename ?? item.path);
};

export const isImage = (item: ClassifiableMedia | null | undefined): boolean => classify(item) === MediaType.Image;

export const isVideo = (item: ClassifiableMedia | null | undefined): boolean => classify(item) === MediaType.Video;

export const isDocument = (item: ClassifiableMedia | null | undefined): boolean => classify(item) === MediaType.Document;

/** Whether the item is an animated GIF — several platforms treat it specially. */
export const isGif = (item: ClassifiableMedia | null | undefined): boolean => normalizeMime(item?.mime_type) === GIF_MIME;

export const isMov = (item: ClassifiableMedia | null | undefined): boolean =>
    normalizeMime(item?.mime_type) === MOV_MIME || extensionOf(item?.original_filename ?? item?.path) === 'mov';
