<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\YouTube\Category;
use Illuminate\Support\Str;

/**
 * YouTube upload metadata other than the description: title and
 * category. The explicit title wins; without one the title is the first
 * non-empty line of the post's plain text, the same rule the composer uses
 * to fill the Title field.
 */
class YouTubeMetadata
{
    public const int TITLE_MAX_LENGTH = 100;

    /**
     * @param  array<string, mixed>|null  $meta
     * @param  string  $content  the post text after ContentSanitizer, as publishers and previews pass it
     */
    public static function title(?array $meta, string $content): string
    {
        $title = data_get($meta, 'title');

        if (is_string($title) && filled(Str::trim($title))) {
            return Str::trim($title);
        }

        $firstLine = collect(explode("\n", $content))
            ->map(fn (string $line): string => Str::trim(str_replace(['<', '>'], '', $line)))
            ->first(fn (string $line): bool => $line !== '', '');

        return mb_substr($firstLine, 0, self::TITLE_MAX_LENGTH);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function categoryId(?array $meta): string
    {
        return (Category::tryFrom((string) data_get($meta, 'category_id')) ?? Category::DEFAULT)->value;
    }

    /**
     * YouTube rejects an upload without a title (`invalidTitle`), so a post with
     * neither a title nor any text cannot publish there.
     *
     * @param  array<string, mixed>|null  $meta
     * @param  string  $content  the post text after ContentSanitizer
     * @return array{0: string, 1: string}|null
     */
    public static function missingTitleViolation(?array $meta, string $content): ?array
    {
        return self::title($meta, $content) === '' ? ['title', __('posts.form.youtube.title_required')] : null;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public static function violation(mixed $meta): ?array
    {
        $title = data_get($meta, 'title');

        if (is_string($title) && (str_contains($title, '<') || str_contains($title, '>'))) {
            return ['title', __('posts.form.youtube.title_invalid')];
        }

        return null;
    }
}
