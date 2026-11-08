<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Media\Actions\DeleteMedia;
use Larasell\Larasell\Media\Actions\UpdateMedia;
use Larasell\Larasell\Media\Actions\UpdateMediaPayload;
use Larasell\Larasell\Media\Actions\UploadMedia;
use Larasell\Larasell\Media\Actions\UploadMediaPayload;
use Larasell\Larasell\Media\AssetVisibility;
use Larasell\Larasell\Media\Events\MediaDeleted;
use Larasell\Larasell\Media\Events\MediaUpdated;
use Larasell\Larasell\Media\Events\MediaUploaded;
use Larasell\Larasell\Media\Exceptions\PrivateMedia;
use Larasell\Larasell\Media\Exceptions\UnsupportedMedia;
use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\MediaType;
use Larasell\Larasell\Media\Models\Media;
use Larasell\Larasell\Translatable;

beforeEach(function () {
    Storage::fake('media');
    Storage::fake('private-media');
    config()->set('larasell.media.disks', ['public' => 'media', 'private' => 'private-media']);
});

function uploadMedia(UploadedFile $file, ?Translatable $alt = null, AssetVisibility $visibility = AssetVisibility::Public): Media
{
    return app(UploadMedia::class)->handle(new UploadMediaPayload($file, $alt, $visibility));
}

it('uploads an image with its original asset, and announces it', function () {
    // Arrange
    Event::fake([MediaUploaded::class]);

    // Act
    $media = uploadMedia(UploadedFile::fake()->image('boots.png', 640, 480), Translatable::fromString('Boots', 'en'));

    // Assert
    $original = $media->original;
    expect($media->type)->toBe(MediaType::Image)
        ->and($media->alt?->get('en'))->toBe('Boots')
        ->and($original->file_name)->toBe('boots.png')
        ->and($original->mime_type)->toBe('image/png')
        ->and([$original->width, $original->height])->toBe([640, 480])
        ->and($original->size)->toBeGreaterThan(0)
        ->and($original->checksum)->toHaveLength(64)
        ->and($original->disk)->toBe('media')
        ->and($original->visibility)->toBe(AssetVisibility::Public);
    Storage::disk('media')->assertExists($original->path);
    expect($original->path)->toStartWith('larasell/media/')->not->toContain('boots');
    Event::assertDispatched(MediaUploaded::class, fn (MediaUploaded $event): bool => $event->mediaId->equals(MediaId::of($media)));
});

it('uploads a video, whose size in pixels it does not know', function () {
    // Act
    $media = uploadMedia(UploadedFile::fake()->create('unboxing.mp4', 100, 'video/mp4'));

    // Assert
    expect($media->type)->toBe(MediaType::Video)
        ->and($media->alt)->toBeNull()
        ->and($media->original->mime_type)->toBe('video/mp4')
        ->and([$media->original->width, $media->original->height])->toBe([null, null]);
});

it('refuses files that are neither images nor videos, and stores nothing', function (UploadedFile $file) {
    // Act & Assert
    expect(fn () => uploadMedia($file))->toThrow(UnsupportedMedia::class);
    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('media')->allFiles())->toBe([])
        ->and(Storage::disk('private-media')->allFiles())->toBe([]);
})->with([
    'a PDF' => fn () => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf'),
    // SVGs can carry scripts that run where they're opened.
    'an SVG' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
]);

it('removes the stored file when saving the media fails', function () {
    // Arrange
    Event::listen(MediaUploaded::class, fn () => throw new RuntimeException('Refused'));

    // Act & Assert
    expect(fn () => uploadMedia(UploadedFile::fake()->image('boots.png')))->toThrow(RuntimeException::class);
    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('media')->allFiles())->toBe([])
        ->and(Storage::disk('private-media')->allFiles())->toBe([]);
});

it('links to the original file on its disk', function () {
    // Arrange
    $media = uploadMedia(UploadedFile::fake()->image('boots.png'));

    // Act
    $url = $media->original->url();

    // Assert
    expect($url)->toBe(Storage::disk('media')->url($media->original->path));
});

it('changes the alt text, and announces only real changes', function () {
    // Arrange
    $media = uploadMedia(UploadedFile::fake()->image('boots.png'), Translatable::fromString('Boots', 'en'));
    Event::fake([MediaUpdated::class]);

    // Act
    $updated = app(UpdateMedia::class)->handle(MediaId::of($media), new UpdateMediaPayload(alt: new Translatable(['en' => 'Boots', 'de' => 'Stiefel']), title: null, description: null));
    app(UpdateMedia::class)->handle(MediaId::of($media), new UpdateMediaPayload(alt: new Translatable(['en' => 'Boots', 'de' => 'Stiefel']), title: null, description: null));

    // Assert
    expect($updated->alt?->all())->toBe(['en' => 'Boots', 'de' => 'Stiefel'])
        ->and($media->fresh()?->alt?->get('de'))->toBe('Stiefel');
    Event::assertDispatchedTimes(MediaUpdated::class, 1);
    Event::assertDispatched(MediaUpdated::class, fn (MediaUpdated $event): bool => $event->changed === ['alt']);
});

it('clears the alt text', function () {
    // Arrange
    $media = uploadMedia(UploadedFile::fake()->image('boots.png'), Translatable::fromString('Boots', 'en'));

    // Act
    app(UpdateMedia::class)->handle(MediaId::of($media), new UpdateMediaPayload(alt: null, title: null, description: null));

    // Assert
    expect($media->fresh()?->alt)->toBeNull();
});

it('deletes the media with its assets and files, and announces it', function () {
    // Arrange
    $media = uploadMedia(UploadedFile::fake()->image('boots.png'));
    $path = $media->original->path;
    Event::fake([MediaDeleted::class]);

    // Act
    app(DeleteMedia::class)->handle(MediaId::of($media));

    // Assert
    expect(Media::query()->count())->toBe(0)
        ->and(DB::table('larasell_media_assets')->count())->toBe(0);
    Storage::disk('media')->assertMissing($path);
    Event::assertDispatched(MediaDeleted::class, fn (MediaDeleted $event): bool => $event->mediaId->equals(MediaId::of($media)));
});

it('keeps the files when a listener refuses the delete', function () {
    // Arrange
    $media = uploadMedia(UploadedFile::fake()->image('boots.png'));
    Event::listen(MediaDeleted::class, fn () => throw new RuntimeException('Still used'));

    // Act & Assert
    expect(fn () => app(DeleteMedia::class)->handle(MediaId::of($media)))->toThrow(RuntimeException::class);
    expect(Media::query()->count())->toBe(1);
    Storage::disk('media')->assertExists($media->original->path);
});

it('keeps uploads private unless told otherwise', function () {
    // Act
    $media = app(UploadMedia::class)->handle(new UploadMediaPayload(UploadedFile::fake()->image('invoice.png')));

    // Assert
    expect($media->original->visibility)->toBe(AssetVisibility::Private)
        ->and($media->original->disk)->toBe('private-media');
    Storage::disk('private-media')->assertExists($media->original->path);
    expect(Storage::disk('media')->allFiles())->toBe([]);
});

it('gives private files no lasting URL, only one that expires', function () {
    // Arrange
    $media = uploadMedia(UploadedFile::fake()->image('invoice.png'), visibility: AssetVisibility::Private);
    Storage::disk('private-media')->buildTemporaryUrlsUsing(fn (string $path, DateTimeInterface $expiresAt): string => "https://files.test/{$path}?expires={$expiresAt->getTimestamp()}");
    $expiresAt = now()->addMinutes(5);

    // Act & Assert
    expect(fn () => $media->original->url())->toThrow(PrivateMedia::class);
    expect($media->original->temporaryUrl($expiresAt))
        ->toBe("https://files.test/{$media->original->path}?expires={$expiresAt->getTimestamp()}");
});

it('moves the files to the other disk when the visibility changes, and announces it', function (AssetVisibility $from, AssetVisibility $to, string $fromDisk, string $toDisk) {
    // Arrange
    $media = uploadMedia(UploadedFile::fake()->image('boots.png'), visibility: $from);
    $path = $media->original->path;
    Event::fake([MediaUpdated::class]);

    // Act
    app(UpdateMedia::class)->handle(MediaId::of($media), new UpdateMediaPayload(alt: null, title: null, description: null, visibility: $to));

    // Assert
    $original = $media->fresh()?->original;
    expect($original?->visibility)->toBe($to)
        ->and($original?->disk)->toBe($toDisk);
    Storage::disk($toDisk)->assertExists((string) $original?->path);
    Storage::disk($fromDisk)->assertMissing($path);
    Event::assertDispatched(MediaUpdated::class, fn (MediaUpdated $event): bool => $event->changed === ['visibility']);
})->with([
    'public to private' => [AssetVisibility::Public, AssetVisibility::Private, 'media', 'private-media'],
    'private to public' => [AssetVisibility::Private, AssetVisibility::Public, 'private-media', 'media'],
]);

it('keeps the file where it was when a listener refuses the visibility change', function () {
    // Arrange
    $media = uploadMedia(UploadedFile::fake()->image('boots.png'), visibility: AssetVisibility::Private);
    Event::listen(MediaUpdated::class, fn () => throw new RuntimeException('Refused'));

    // Act & Assert
    expect(fn () => app(UpdateMedia::class)->handle(MediaId::of($media), new UpdateMediaPayload(alt: null, title: null, description: null, visibility: AssetVisibility::Public)))
        ->toThrow(RuntimeException::class);
    expect($media->fresh()?->original->disk)->toBe('private-media')
        ->and(Storage::disk('media')->allFiles())->toBe([]);
    Storage::disk('private-media')->assertExists($media->original->path);
});

it('leaves the visibility alone when the update does not name one', function () {
    // Arrange
    $media = uploadMedia(UploadedFile::fake()->image('boots.png'), visibility: AssetVisibility::Private);

    // Act
    app(UpdateMedia::class)->handle(MediaId::of($media), new UpdateMediaPayload(alt: Translatable::fromString('Boots', 'en'), title: null, description: null));

    // Assert
    expect($media->fresh()?->original->visibility)->toBe(AssetVisibility::Private);
});

it('uploads media with its title and description', function () {
    // Act
    $media = app(UploadMedia::class)->handle(new UploadMediaPayload(
        UploadedFile::fake()->image('boots.png'),
        title: Translatable::fromString('Summer campaign', 'en'),
        description: Translatable::fromString('Shot by Studio X.', 'en'),
    ));

    // Assert
    expect($media->fresh()?->title?->get('en'))->toBe('Summer campaign')
        ->and($media->fresh()?->description?->get('en'))->toBe('Shot by Studio X.');
});

it('changes the title and description, and names only those as changed', function () {
    // Arrange
    $media = uploadMedia(UploadedFile::fake()->image('boots.png'), Translatable::fromString('Boots', 'en'));
    Event::fake([MediaUpdated::class]);

    // Act
    app(UpdateMedia::class)->handle(MediaId::of($media), new UpdateMediaPayload(
        alt: Translatable::fromString('Boots', 'en'),
        title: new Translatable(['en' => 'Front', 'de' => 'Vorne']),
        description: Translatable::fromString('Licensed until 2027.', 'en'),
    ));

    // Assert
    $fresh = $media->fresh();
    expect($fresh?->title?->all())->toBe(['en' => 'Front', 'de' => 'Vorne'])
        ->and($fresh?->description?->get('en'))->toBe('Licensed until 2027.')
        ->and($fresh?->alt?->get('en'))->toBe('Boots');
    Event::assertDispatched(MediaUpdated::class, fn (MediaUpdated $event): bool => $event->changed === ['title', 'description']);
});

it('clears the title and description', function () {
    // Arrange
    $media = app(UploadMedia::class)->handle(new UploadMediaPayload(
        UploadedFile::fake()->image('boots.png'),
        title: Translatable::fromString('Front', 'en'),
        description: Translatable::fromString('Notes', 'en'),
    ));

    // Act
    app(UpdateMedia::class)->handle(MediaId::of($media), new UpdateMediaPayload(alt: null, title: null, description: null));

    // Assert
    expect($media->fresh()?->title)->toBeNull()
        ->and($media->fresh()?->description)->toBeNull();
});
