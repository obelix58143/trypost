<?php

declare(strict_types=1);

namespace App\Events\Ai;

class PostCreationReady
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __unserialize(array $values): void {}

    /**
     * @return array<int, never>
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
