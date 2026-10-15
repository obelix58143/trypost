<?php

declare(strict_types=1);

namespace App\Actions\SocialAccount;

use App\Models\SocialAccount;
use App\Services\Social\PinterestPublisher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ListPinterestBoards
{
    /**
     * @return array{boards: list<array{id: string, name: string, cover_url: string|null}>, truncated: bool}
     */
    public static function execute(SocialAccount $account, bool $interactive = false): array
    {
        $publisher = app(PinterestPublisher::class);
        $result = ($interactive ? $publisher->interactive() : $publisher)->getBoards($account);

        $boards = Collection::make(data_get($result, 'boards', []))
            ->map(fn (mixed $board): array => self::present($board))
            ->filter(fn (array $board): bool => $board['id'] !== '')
            ->values()
            ->all();

        return [
            'boards' => $boards,
            'truncated' => (bool) data_get($result, 'truncated', false),
        ];
    }

    /**
     * The boards the web composer shows, kept for five minutes per account.
     * Read with the interactive client: a person is waiting on it.
     *
     * @return array{boards: list<array{id: string, name: string, cover_url: string|null}>, truncated: bool}
     */
    public static function cached(SocialAccount $account): array
    {
        return Cache::remember(self::cacheKey($account->id), now()->addMinutes(5), fn (): array => self::execute($account, interactive: true));
    }

    /**
     * Reads the boards from Pinterest and replaces the cached copy.
     *
     * @return array{boards: list<array{id: string, name: string, cover_url: string|null}>, truncated: bool}
     */
    public static function refresh(SocialAccount $account): array
    {
        $boards = self::execute($account);

        Cache::put(self::cacheKey($account->id), $boards, now()->addMinutes(5));

        return $boards;
    }

    public static function forget(string $accountId): void
    {
        Cache::forget(self::cacheKey($accountId));
    }

    private static function cacheKey(string $accountId): string
    {
        return "pinterest:boards:{$accountId}";
    }

    /**
     * The board fields the app reads from a Pinterest API v5 board object.
     *
     * @return array{id: string, name: string, cover_url: string|null}
     */
    public static function present(mixed $board): array
    {
        $coverUrl = data_get($board, 'media.image_cover_url');

        return [
            'id' => (string) data_get($board, 'id'),
            'name' => (string) data_get($board, 'name'),
            'cover_url' => filled($coverUrl) ? (string) $coverUrl : null,
        ];
    }
}
