<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Generator;
use Illuminate\Support\Arr;

/**
 * Reads a video's duration from the `moov > mvhd` atom of an MP4 / MOV file.
 * Only atom headers are read, so a file whose `moov` sits after a large `mdat`
 * costs a handful of small reads rather than a full download.
 */
final class VideoDurationProbe
{
    private const ATOM_HEADER_BYTES = 8;

    private const ATOM_LARGE_HEADER_BYTES = 16;

    private const MVHD_V0_BYTES = 20;

    private const MVHD_V1_BYTES = 32;

    /**
     * @param  Closure(int, int): string  $reader  Returns up to `$length` bytes starting at `$offset`.
     */
    private function __construct(
        private readonly Closure $reader,
        private readonly int $size,
    ) {}

    public static function fromFile(string $path): ?float
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            return self::fromReader(
                static function (int $offset, int $length) use ($handle): string {
                    if ($length < 1 || fseek($handle, $offset) !== 0) {
                        return '';
                    }

                    return (string) fread($handle, $length);
                },
                (int) data_get(fstat($handle) ?: [], 'size', 0),
            );
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  Closure(int, int): string  $read  Returns up to `$length` bytes starting at `$offset`.
     */
    public static function fromReader(Closure $read, int $totalSize): ?float
    {
        return (new self($read, max(0, $totalSize)))->duration();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function mergeInto(array $meta, ?float $duration): array
    {
        return $duration === null || $duration <= 0
            ? $meta
            : [...$meta, 'duration' => round($duration, 2)];
    }

    private function duration(): ?float
    {
        $moov = $this->payload('moov', 0, $this->size);
        $mvhd = $moov === null ? null : $this->payload('mvhd', ...$moov);

        return $mvhd === null ? null : $this->seconds(...$mvhd);
    }

    /**
     * Payload bounds of the first `$type` atom between `$from` and `$to`.
     *
     * @return array{int, int}|null
     */
    private function payload(string $type, int $from, int $to): ?array
    {
        return Arr::first(
            $this->atoms($from, $to),
            fn (array $bounds, string $atomType): bool => $atomType === $type,
        );
    }

    /**
     * Walks the atoms laid out between `$from` and `$to`, yielding each type
     * with its payload bounds. An atom claiming more bytes than remain is
     * clamped, and a header that cannot be read ends the walk.
     *
     * @return Generator<string, array{int, int}>
     */
    private function atoms(int $from, int $to): Generator
    {
        for ($offset = $from; $offset + self::ATOM_HEADER_BYTES <= $to; $offset += $size) {
            $header = $this->header($offset, $to);

            if ($header === null) {
                return;
            }

            [$size, $type, $headerBytes] = $header;
            $size = min($size, $to - $offset);

            yield $type => [$offset + $headerBytes, $offset + $size];
        }
    }

    /**
     * @return array{int, string, int}|null
     */
    private function header(int $offset, int $to): ?array
    {
        $bytes = $this->read($offset, min(self::ATOM_LARGE_HEADER_BYTES, $to - $offset));
        $fields = strlen($bytes) >= self::ATOM_HEADER_BYTES
            ? unpack('Nsize/a4type', $bytes)
            : false;

        if ($fields === false) {
            return null;
        }

        ['size' => $size, 'type' => $type] = $fields;
        $headerBytes = self::ATOM_HEADER_BYTES;

        if ($size === 1 && strlen($bytes) === self::ATOM_LARGE_HEADER_BYTES) {
            $size = (int) data_get(unpack('J', $bytes, self::ATOM_HEADER_BYTES), 1, 0);
            $headerBytes = self::ATOM_LARGE_HEADER_BYTES;
        } elseif ($size === 0) {
            $size = $to - $offset;
        }

        return $size < $headerBytes ? null : [$size, $type, $headerBytes];
    }

    /**
     * `mvhd` starts with a version byte; version 1 widens the creation and
     * modification times to 64 bits, which pushes `timescale` and `duration`
     * from offsets 12 / 16 to 20 / 24 and makes `duration` 64-bit as well.
     */
    private function seconds(int $start, int $end): ?float
    {
        $mvhd = $this->read($start, min($end - $start, self::MVHD_V1_BYTES));
        $version1 = strlen($mvhd) > 0 && ord($mvhd[0]) === 1;

        if (strlen($mvhd) < ($version1 ? self::MVHD_V1_BYTES : self::MVHD_V0_BYTES)) {
            return null;
        }

        $fields = unpack(
            $version1 ? 'x20/Ntimescale/Jduration' : 'x12/Ntimescale/Nduration',
            $mvhd,
        );

        if ($fields === false) {
            return null;
        }

        ['timescale' => $timescale, 'duration' => $duration] = $fields;

        return $timescale > 0 && $duration > 0 ? $duration / $timescale : null;
    }

    private function read(int $offset, int $length): string
    {
        return $length < 1 ? '' : ($this->reader)($offset, $length);
    }
}
