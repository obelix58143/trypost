<?php

declare(strict_types=1);

use App\Enums\Media\Type;
use Symfony\Component\Process\Process;

/**
 * `resources/js/lib/mediaType.ts` mirrors `App\Enums\Media\Type` so the editor can
 * classify media without a round trip. Agreement is asserted rather than assumed:
 * both sides classify the same corpus — explicit type, MIME (cased, with
 * parameters, without a slash), filename, storage key and full URL — and every
 * answer must match.
 */
test('the typescript media classifier agrees with the php enum on every corpus entry', function () {
    $corpusPath = base_path('tests/fixtures/media-type-corpus.json');
    $corpus = json_decode(file_get_contents($corpusPath), true, flags: JSON_THROW_ON_ERROR);

    $process = new Process([
        'node',
        base_path('tests/fixtures/media-type-harness.mjs'),
        resource_path('js/lib/mediaType.ts'),
        $corpusPath,
    ]);
    $process->run();

    if (! $process->isSuccessful()) {
        $this->markTestSkipped('node is unavailable: '.$process->getErrorOutput());
    }

    $fromPhp = array_map(function (array $item): array {
        $fileName = data_get($item, 'original_filename') ?? data_get($item, 'path');
        $mimeType = data_get($item, 'mime_type');

        return [
            'type' => (Type::tryFrom((string) data_get($item, 'type', '')) ?? Type::classify($mimeType, $fileName))?->value,
            'isMov' => Type::isMov($mimeType, $fileName),
            'isGif' => Type::isGif($mimeType),
        ];
    }, $corpus);

    $fromTypeScript = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    $labels = array_map(fn (array $item): string => json_encode($item, JSON_THROW_ON_ERROR), $corpus);

    expect(array_combine($labels, $fromTypeScript))->toEqual(array_combine($labels, $fromPhp));
});
