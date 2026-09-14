<?php

declare(strict_types=1);

use App\Enums\Media\Type as MediaType;
use App\Models\Media;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

test('media belongs to mediable', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');

    expect($media->mediable->id)->toBe($workspace->id);
});

test('media has url attribute', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');

    expect($media->url)->not->toBeEmpty();
});

test('media casts type to enum', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');

    expect($media->type)->toBeInstanceOf(MediaType::class);
});

test('media casts size to integer', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');

    expect($media->size)->toBeInt();
});

test('media casts meta to array', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo', ['key' => 'value']);

    expect($media->meta)->toBeArray();
    expect($media->meta['key'])->toBe('value');
});

test('media deletes file from storage when deleted', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');
    $path = $media->path;

    Storage::assertExists($path);

    $media->delete();

    Storage::assertMissing($path);
});

test('toPostMediaItem carries the measured meta and only puts alt text on images', function () {
    $workspace = Workspace::factory()->create();
    $video = Media::factory()->video()->for($workspace, 'mediable')->create(['meta' => ['duration' => 12.5]]);
    $image = Media::factory()->for($workspace, 'mediable')->create(['meta' => ['width' => 10, 'height' => 20]]);
    $document = Media::factory()->document()->for($workspace, 'mediable')->create();

    expect($video->toPostMediaItem('ignored on video'))->toEqual([
        'id' => $video->id,
        'path' => $video->path,
        'url' => $video->url,
        'type' => 'video',
        'mime_type' => 'video/mp4',
        'original_filename' => $video->original_filename,
        'size' => $video->size,
        'meta' => ['duration' => 12.5],
    ])
        ->and(data_get($image->toPostMediaItem('A red bicycle'), 'meta'))->toEqual(['width' => 10, 'height' => 20, 'alt_text' => 'A red bicycle'])
        ->and(data_get($image->toPostMediaItem(''), 'meta'))->toEqual(['width' => 10, 'height' => 20])
        ->and($document->toPostMediaItem('ignored on pdf'))->not->toHaveKey('meta');
});

test('media can get temporary url', function () {
    // Use a driver that supports temporary URLs
    Storage::fake('s3');
    config(['filesystems.default' => 's3']);

    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');

    // The fake driver returns a basic URL format
    $url = $media->getTemporaryUrl(30);

    expect($url)->toBeString();
});
