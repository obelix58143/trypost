<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\Repurpose\SourceFormat;
use Carbon\CarbonInterface;
use Closure;

readonly class SourceMedia
{
    /**
     * @param  (Closure(): ?string)|null  $downloadUrlResolver  Looks the file up only when the media is new to the caller.
     */
    public function __construct(
        public string $id,
        public ?SourceFormat $format,
        public ?string $downloadUrl,
        public string $caption,
        public ?string $permalink,
        public ?CarbonInterface $createdAt,
        public ?Closure $downloadUrlResolver = null,
    ) {}

    public function predates(?CarbonInterface $watermark): bool
    {
        return $watermark !== null
            && $this->createdAt !== null
            && $this->createdAt->lessThanOrEqualTo($watermark);
    }

    public function resolveDownloadUrl(): ?string
    {
        if ($this->downloadUrl !== null || $this->downloadUrlResolver === null) {
            return $this->downloadUrl;
        }

        return ($this->downloadUrlResolver)();
    }
}
