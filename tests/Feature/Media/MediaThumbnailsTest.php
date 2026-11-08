<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Media\Actions\DeleteMedia;
use Larasell\Larasell\Media\Actions\UploadMedia;
use Larasell\Larasell\Media\Actions\UploadMediaPayload;
use Larasell\Larasell\Media\AssetVisibility;
use Larasell\Larasell\Media\Events\MediaThumbnailGenerated;
use Larasell\Larasell\Media\Exceptions\MediaNotProcessed;
use Larasell\Larasell\Media\Exceptions\MediaNotShared;
use Larasell\Larasell\Media\Jobs\GenerateThumbnail;
use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\Models\Media;
use Larasell\Larasell\Media\Models\MediaAsset;

beforeEach(function () {
    Storage::fake('media');
    Storage::fake('private-media');
    config()->set('larasell.media.disks', ['public' => 'media', 'private' => 'private-media']);
});

function thumbnailOf(Media $media): ?MediaAsset
{
    return $media->assets()->where('role', MediaAsset::THUMBNAIL)->first();
}

/** A one-second MP4 of one color, made with FFmpeg, so the poster's color can be checked. */
function solidVideo(string $color = 'red', string $size = '640x360'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'video').'.mp4';
    exec('ffmpeg -loglevel error -y -f lavfi -i '.escapeshellarg("color=c={$color}:s={$size}:d=1").' -pix_fmt yuv420p '.escapeshellarg($path), result_code: $code);
    expect($code)->toBe(0);

    return new UploadedFile($path, 'unboxing.mp4', 'video/mp4', null, true);
}

function uploadForThumbnail(UploadedFile $file, AssetVisibility $visibility = AssetVisibility::Public): Media
{
    return app(UploadMedia::class)->handle(new UploadMediaPayload($file, visibility: $visibility));
}

it('queues a thumbnail for an uploaded image on the media queue', function () {
    // Arrange
    Queue::fake();
    config()->set('larasell.media.thumbnails.queue', 'images');

    // Act
    $media = uploadForThumbnail(UploadedFile::fake()->image('boots.png', 1600, 1200));

    // Assert
    Queue::assertPushedOn('images', GenerateThumbnail::class, fn (GenerateThumbnail $job): bool => $job->mediaId->equals(MediaId::of($media)));
    expect(thumbnailOf($media))->toBeNull();
});

it('queues a thumbnail for an uploaded video too', function () {
    // Arrange
    Queue::fake();

    // Act
    $media = uploadForThumbnail(UploadedFile::fake()->create('unboxing.mp4', 100, 'video/mp4'));

    // Assert
    Queue::assertPushed(GenerateThumbnail::class, fn (GenerateThumbnail $job): bool => $job->mediaId->equals(MediaId::of($media)));
});

it('makes a WebP poster of a video\'s first frame, just as visible as the video', function () {
    // Arrange
    Queue::fake();
    $media = uploadForThumbnail(solidVideo('red', '640x360'), AssetVisibility::Private);

    // Act
    app()->call([new GenerateThumbnail(MediaId::of($media)), 'handle']);

    // Assert
    $thumbnail = thumbnailOf($media);
    expect($thumbnail)->not->toBeNull()
        ->and($thumbnail->mime_type)->toBe('image/webp')
        ->and($thumbnail->file_name)->toBe('unboxing.webp')
        ->and($thumbnail->visibility)->toBe(AssetVisibility::Private)
        ->and([$thumbnail->width, $thumbnail->height])->toBe([400, 225]);
    $image = imagecreatefromstring((string) Storage::disk('private-media')->get($thumbnail->path));
    $color = imagecolorsforindex($image, (int) imagecolorat($image, 200, 112));
    expect($color['red'])->toBeGreaterThan(200)
        ->and($color['green'])->toBeLessThan(60)
        ->and($color['blue'])->toBeLessThan(60);
});

it('announces the thumbnail it made, so apps can build on it', function () {
    // Arrange
    Queue::fake();
    Event::fake([MediaThumbnailGenerated::class]);
    $media = uploadForThumbnail(UploadedFile::fake()->image('boots.png'));

    // Act
    app()->call([new GenerateThumbnail(MediaId::of($media)), 'handle']);

    // Assert
    Event::assertDispatched(MediaThumbnailGenerated::class, fn (MediaThumbnailGenerated $event): bool => $event->mediaId->equals(MediaId::of($media)));
});

it('fails loudly when the worker cannot run FFmpeg', function () {
    // Arrange
    Queue::fake();
    config()->set('larasell.media.ffmpeg', '/nonexistent/ffmpeg');
    $media = uploadForThumbnail(solidVideo());

    // Act & Assert
    expect(fn () => app()->call([new GenerateThumbnail(MediaId::of($media)), 'handle']))
        ->toThrow(MediaNotProcessed::class);
    expect(thumbnailOf($media))->toBeNull();
});

it('makes a smaller WebP of the image, next to the original and just as visible', function (AssetVisibility $visibility, string $disk) {
    // Arrange
    Queue::fake();
    config()->set('larasell.media.thumbnails.size', 400);
    $media = uploadForThumbnail(UploadedFile::fake()->image('boots.png', 1600, 1200), $visibility);

    // Act
    app()->call([new GenerateThumbnail(MediaId::of($media)), 'handle']);

    // Assert
    $thumbnail = thumbnailOf($media);
    expect($thumbnail?->mime_type)->toBe('image/webp')
        ->and([$thumbnail?->width, $thumbnail?->height])->toBe([400, 300])
        ->and($thumbnail?->visibility)->toBe($visibility)
        ->and($thumbnail?->disk)->toBe($disk);
    $stored = Storage::disk($disk)->get((string) $thumbnail?->path);
    expect(getimagesizefromstring((string) $stored)['mime'] ?? null)->toBe('image/webp')
        ->and($thumbnail?->size)->toBe(strlen((string) $stored));
})->with([
    'public' => [AssetVisibility::Public, 'media'],
    'private' => [AssetVisibility::Private, 'private-media'],
]);

it('fits tall images by their height and never enlarges small ones', function (array $original, array $expected) {
    // Arrange
    Queue::fake();
    config()->set('larasell.media.thumbnails.size', 400);
    $media = uploadForThumbnail(UploadedFile::fake()->image('boots.png', ...$original));

    // Act
    app()->call([new GenerateThumbnail(MediaId::of($media)), 'handle']);

    // Assert
    $thumbnail = thumbnailOf($media);
    expect([$thumbnail?->width, $thumbnail?->height])->toBe($expected);
})->with([
    'tall' => [[600, 1200], [200, 400]],
    'small' => [[120, 80], [120, 80]],
]);

it('replaces the thumbnail it made before', function () {
    // Arrange
    Queue::fake();
    $media = uploadForThumbnail(UploadedFile::fake()->image('boots.png', 800, 800));
    app()->call([new GenerateThumbnail(MediaId::of($media)), 'handle']);
    $old = thumbnailOf($media)?->path;

    // Act
    app()->call([new GenerateThumbnail(MediaId::of($media)), 'handle']);

    // Assert
    expect($media->assets()->where('role', MediaAsset::THUMBNAIL)->count())->toBe(1);
    Storage::disk('media')->assertMissing((string) $old);
    Storage::disk('media')->assertExists((string) thumbnailOf($media)?->path);
});

it('fails loudly when the worker cannot reach the original, e.g. on a disk that is not shared', function () {
    // Arrange
    Queue::fake();
    $media = uploadForThumbnail(UploadedFile::fake()->image('boots.png', 800, 800));
    // As a worker on another server sees a local disk: without the web server's files.
    Storage::disk('media')->delete($media->original->path);

    // Act & Assert
    expect(fn () => app()->call([new GenerateThumbnail(MediaId::of($media)), 'handle']))
        ->toThrow(MediaNotShared::class);
    expect(thumbnailOf($media))->toBeNull();
});

it('does nothing for media deleted before the job ran', function () {
    // Arrange
    Queue::fake();
    $media = uploadForThumbnail(UploadedFile::fake()->image('boots.png'));
    app(DeleteMedia::class)->handle(MediaId::of($media));

    // Act
    app()->call([new GenerateThumbnail(MediaId::of($media)), 'handle']);

    // Assert
    expect(Storage::disk('media')->allFiles())->toBe([]);
});

it('makes the thumbnail right away on a sync queue, as on a single machine', function () {
    // Act
    $media = uploadForThumbnail(UploadedFile::fake()->image('boots.png', 1600, 1200));

    // Assert
    expect(thumbnailOf($media)?->width)->toBe(config('larasell.media.thumbnails.size'));
});

it('queues thumbnails for the images and videos that have none yet', function () {
    // Arrange
    Queue::fake();
    $done = uploadForThumbnail(UploadedFile::fake()->image('done.png'));
    app()->call([new GenerateThumbnail(MediaId::of($done)), 'handle']);
    $missing = uploadForThumbnail(UploadedFile::fake()->image('missing.png'));
    uploadForThumbnail(UploadedFile::fake()->create('unboxing.mp4', 100, 'video/mp4'));
    Queue::fake();

    // Act & Assert
    $this->artisan('larasell:media:thumbnails')
        ->expectsOutputToContain('Queued 2 thumbnail')
        ->assertSuccessful();
    Queue::assertPushed(GenerateThumbnail::class, 2);
    Queue::assertPushed(GenerateThumbnail::class, fn (GenerateThumbnail $job): bool => $job->mediaId->equals(MediaId::of($missing)));
});

it('queues thumbnails for every image when forced, e.g. after the size changed', function () {
    // Arrange
    Queue::fake();
    $done = uploadForThumbnail(UploadedFile::fake()->image('done.png'));
    app()->call([new GenerateThumbnail(MediaId::of($done)), 'handle']);
    uploadForThumbnail(UploadedFile::fake()->image('missing.png'));
    Queue::fake();

    // Act & Assert
    $this->artisan('larasell:media:thumbnails', ['--force' => true])->assertSuccessful();
    Queue::assertPushed(GenerateThumbnail::class, 2);
});
