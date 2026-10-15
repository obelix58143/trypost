<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array TARGETS = [
        ['posts', 'media'],
        ['ideas', 'media'],
        ['post_platforms', 'meta'],
    ];

    public function up(): void
    {
        foreach (self::TARGETS as [$table, $column]) {
            DB::table($table)
                ->whereLike($column, '%giphy%')
                ->select(['id', $column])
                ->chunkById(500, function ($rows) use ($table, $column): void {
                    foreach ($rows as $row) {
                        $decoded = json_decode((string) $row->{$column}, true);

                        if (! is_array($decoded)) {
                            continue;
                        }

                        $changed = false;

                        if ($column === 'media') {
                            $decoded = $this->clearItems($decoded, $changed);
                        } else {
                            $replies = data_get($decoded, 'thread_replies');

                            if (! is_array($replies)) {
                                continue;
                            }

                            foreach ($replies as $index => $reply) {
                                if (is_array($reply) && is_array(data_get($reply, 'media'))) {
                                    $replies[$index]['media'] = $this->clearItems($reply['media'], $changed);
                                }
                            }

                            $decoded['thread_replies'] = $replies;
                        }

                        if ($changed) {
                            DB::table($table)
                                ->where('id', $row->id)
                                ->update([$column => json_encode($decoded)]);
                        }
                    }
                });
        }
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return array<int|string, mixed>
     */
    private function clearItems(array $items, bool &$changed): array
    {
        foreach ($items as $index => $item) {
            if (is_array($item) && data_get($item, 'source') === 'giphy') {
                $items[$index]['source'] = null;
                $changed = true;
            }
        }

        return $items;
    }
};
