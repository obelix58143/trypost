<?php

declare(strict_types=1);

namespace App\Support\Inertia;

use Closure;
use Inertia\OnceProp;

/**
 * A once prop whose key is resolved only when Inertia reads it, so a partial
 * reload that leaves the prop out never pays for computing the key.
 */
class LazilyKeyedOnceProp extends OnceProp
{
    private ?string $resolvedKey = null;

    /**
     * @param  Closure(): string  $keyResolver
     */
    public function __construct(callable $callback, private readonly Closure $keyResolver)
    {
        parent::__construct($callback);
    }

    public function getKey(): ?string
    {
        return $this->resolvedKey ??= ($this->keyResolver)();
    }
}
