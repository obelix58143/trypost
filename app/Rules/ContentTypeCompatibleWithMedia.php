<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Media\Type as MediaType;
use App\Enums\PostPlatform\ContentType;
use App\Models\Post;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Number;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\ValidationException;

class ContentTypeCompatibleWithMedia implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  array<int, array<string, mixed>>|null  $fallbackMedia  Stored media used
     *                                                                when the request omits the `media` key entirely — lets API/MCP partial
     *                                                                updates (which don't resubmit media) validate a content_type against the
     *                                                                post's already-stored media.
     */
    public function __construct(private ?array $fallbackMedia = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Validate every enabled platform's stored content_type against the post's
     * stored media. Used by publish flows that don't resubmit media (e.g. the
     * MCP publish tool) — the media-side mirror of
     * PostPlatformMetaRules::assertStoredPostPublishable().
     *
     * @throws ValidationException
     */
    public static function assertStoredPostCompatible(Post $post): void
    {
        $errors = self::errorsFor(
            self::entriesForUpdate($post, null),
            (array) ($post->media ?? []),
        );

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The per-platform entries to validate for a post update: each platform's
     * effective content_type (resubmitted in this request, else its stored
     * value), keyed by the error path the caller surfaces. When $requestPlatforms
     * is null, the post's currently-enabled platforms are used.
     *
     * @param  array<int, mixed>|null  $requestPlatforms
     * @return array<int, array{key: string, content_type: string|null}>
     */
    public static function entriesForUpdate(Post $post, ?array $requestPlatforms): array
    {
        if (is_array($requestPlatforms)) {
            $stored = $post->postPlatforms()->get()->keyBy('id');

            return collect($requestPlatforms)->map(fn ($platform, $index): array => [
                'key' => "platforms.{$index}.content_type",
                'content_type' => data_get($platform, 'content_type')
                    ?? $stored->get(data_get($platform, 'id'))?->content_type?->value,
            ])->all();
        }

        return $post->postPlatforms()->enabled()->get()->values()
            ->map(fn ($postPlatform, $index): array => [
                'key' => "platforms.{$index}.content_type",
                'content_type' => $postPlatform->content_type?->value,
            ])->all();
    }

    /**
     * Validate a set of platform entries against the given media, returning
     * `[errorKey => message]` for each incompatible content_type.
     *
     * @param  array<int, array{key: string, content_type: string|null}>  $entries
     * @param  array<int, mixed>  $media
     * @return array<string, string>
     */
    public static function errorsFor(array $entries, array $media): array
    {
        $errors = [];

        foreach ($entries as $entry) {
            $contentType = data_get($entry, 'content_type');

            if ($contentType === null) {
                continue;
            }

            (new self($media))->validate(
                $entry['key'],
                (string) $contentType,
                function (string $message) use (&$errors, $entry): void {
                    $errors[$entry['key']] = $message;
                },
            );
        }

        return $errors;
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $contentType = ContentType::tryFrom((string) $value);

        if (! $contentType) {
            return;
        }

        $media = $this->media();

        if ($media === []) {
            if ($contentType->requiresMedia()) {
                $fail(trans('posts.form.warnings.requires_media'));
            }

            return;
        }

        $this->failOnKindRules($contentType, $media, $fail);
        $this->failOnSizeAndDurationCaps($contentType, $media, $fail);
    }

    /**
     * Request `media` when the key is present (including an empty list);
     * otherwise the stored fallback so a partial publish still validates.
     *
     * @return array<int, array<string, mixed>>
     */
    private function media(): array
    {
        $raw = array_key_exists('media', $this->data)
            ? data_get($this->data, 'media', [])
            : ($this->fallbackMedia ?? []);

        return collect($raw)->map(fn (mixed $item): array => (array) $item)->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $media
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function failOnKindRules(ContentType $contentType, array $media, Closure $fail): void
    {
        $items = collect($media);
        $hasImage = $items->contains($this->isImage(...));
        $hasVideo = $items->contains($this->isVideo(...));
        $hasDocument = $items->contains($this->isDocument(...));

        $violations = [
            'no_image_allowed' => $hasImage && ! $contentType->supportsImage(),
            'no_video_allowed' => $hasVideo && ! $contentType->supportsVideo(),
            'no_document_allowed' => $hasDocument && ! $contentType->supportsDocument(),
            'document_not_alone' => $hasDocument && count($media) > 1,
            'no_mixed_media' => $hasImage && $hasVideo && ! $contentType->supportsMixedMedia(),
            'gif_not_allowed' => $items->contains($this->isGif(...)) && ! $contentType->acceptsGif(),
            'mov_not_allowed' => $items->contains($this->isMov(...)) && ! $contentType->acceptsMov(),
        ];

        foreach ($violations as $key => $failed) {
            if ($failed) {
                $fail(trans("posts.form.warnings.{$key}"));
            }
        }
    }

    /**
     * Server-side mirror of the editor's size / duration checks. `meta.duration`
     * is read from the file on upload; an item without it is not checked.
     *
     * @param  array<int, array<string, mixed>>  $media
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function failOnSizeAndDurationCaps(ContentType $contentType, array $media, Closure $fail): void
    {
        $maxDuration = $contentType->maxVideoDurationSec();

        foreach ($media as $item) {
            $size = (int) data_get($item, 'size', 0);
            [$key, $max] = $this->byteCap($contentType, $item);

            if ($size > 0 && $max !== null && $size > $max) {
                $fail(trans("posts.form.warnings.{$key}", [
                    'max' => $this->formatBytes($max, $max),
                    'current' => $this->formatBytes($size, $max, 1),
                ]));

                return;
            }

            $duration = data_get($item, 'meta.duration');

            if ($maxDuration !== null && $this->isVideo($item) && is_numeric($duration) && (float) $duration > $maxDuration) {
                $fail(trans('posts.form.warnings.video_too_long', [
                    'max' => $this->formatDuration($maxDuration),
                    'current' => $this->formatDuration((int) ceil((float) $duration)),
                ]));

                return;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{0: string, 1: int|null}
     */
    private function byteCap(ContentType $contentType, array $item): array
    {
        if ($this->isDocument($item)) {
            return ['document_too_large', $contentType->maxDocumentBytes()];
        }

        if ($this->isVideo($item)) {
            return ['video_too_large', $contentType->maxVideoBytes()];
        }

        return ['image_too_large', $contentType->maxImageBytes()];
    }

    /**
     * Mirrors `formatBytes` in useMedia.ts: a cap declared in decimal megabytes
     * (Bluesky) renders both numbers in decimal units — "300 MB", not "286 MB".
     */
    private function formatBytes(int $bytes, int $cap, int $precision = 0): string
    {
        $decimal = $cap % 1_000_000 === 0 && $cap % (1024 * 1024) !== 0;

        if (! $decimal) {
            return Number::fileSize($bytes, $precision);
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $exponent = min((int) floor(log(max($bytes, 1), 1000)), count($units) - 1);

        return sprintf('%s %s', Number::format($bytes / 1000 ** $exponent, $precision), $units[$exponent]);
    }

    /**
     * Mirrors `formatDurationWords` in date.ts.
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        return $rest === 0 ? "{$minutes}min" : "{$minutes}min {$rest}s";
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isGif(array $item): bool
    {
        return MediaType::isGif(data_get($item, 'mime_type'));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isMov(array $item): bool
    {
        return MediaType::isMov(
            data_get($item, 'mime_type'),
            data_get($item, 'original_filename') ?? data_get($item, 'path'),
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isImage(array $item): bool
    {
        return $this->isType($item, MediaType::Image);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isVideo(array $item): bool
    {
        return $this->isType($item, MediaType::Video);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isDocument(array $item): bool
    {
        return $this->isType($item, MediaType::Document);
    }

    /**
     * A media item matches a type when it carries that explicit `type`, or when
     * its MIME classifies as that type.
     *
     * @param  array<string, mixed>  $item
     */
    private function isType(array $item, MediaType $type): bool
    {
        return data_get($item, 'type') === $type->value
            || MediaType::classify(data_get($item, 'mime_type')) === $type;
    }
}
