<?php

use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Media\Actions\CreateMediaFolder;
use Larasell\Larasell\Media\Actions\CreateMediaFolderPayload;
use Larasell\Larasell\Media\Actions\DeleteMedia;
use Larasell\Larasell\Media\Actions\DeleteMediaFolder;
use Larasell\Larasell\Media\Actions\MoveMedia;
use Larasell\Larasell\Media\Actions\MoveMediaFolder;
use Larasell\Larasell\Media\Actions\MoveMediaFolderPayload;
use Larasell\Larasell\Media\Actions\MoveMediaPayload;
use Larasell\Larasell\Media\Actions\RenameMediaFolder;
use Larasell\Larasell\Media\Actions\RenameMediaFolderPayload;
use Larasell\Larasell\Media\Actions\UploadMedia;
use Larasell\Larasell\Media\Actions\UploadMediaPayload;
use Larasell\Larasell\Media\Events\MediaFolderMoved;
use Larasell\Larasell\Media\Events\MediaMoved;
use Larasell\Larasell\Media\Events\MediaUploaded;
use Larasell\Larasell\Media\Exceptions\InvalidMediaFolderName;
use Larasell\Larasell\Media\Exceptions\MediaFolderCycle;
use Larasell\Larasell\Media\Exceptions\MediaFolderNameTaken;
use Larasell\Larasell\Media\Exceptions\MediaFolderNotEmpty;
use Larasell\Larasell\Media\Exceptions\MediaFolderNotFound;
use Larasell\Larasell\Media\MediaFolderId;
use Larasell\Larasell\Media\MediaFolderName;
use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\MediaMoveSkipReason;
use Larasell\Larasell\Media\Models\Media;
use Larasell\Larasell\Media\Models\MediaFolder;

beforeEach(function () {
    Storage::fake('media');
    Storage::fake('private-media');
    config()->set('larasell.media.disks', ['public' => 'media', 'private' => 'private-media']);
});

function createFolder(string $name, ?MediaFolder $parent = null): MediaFolder
{
    return app(CreateMediaFolder::class)->handle(new CreateMediaFolderPayload(new MediaFolderName($name), $parent === null ? null : MediaFolderId::of($parent)));
}

function moveFolder(MediaFolder $folder, ?MediaFolder $parent): MediaFolder
{
    return app(MoveMediaFolder::class)->handle(MediaFolderId::of($folder), new MoveMediaFolderPayload($parent === null ? null : MediaFolderId::of($parent)));
}

function uploadInto(?MediaFolderId $folderId): Media
{
    return app(UploadMedia::class)->handle(new UploadMediaPayload(UploadedFile::fake()->image('boots.png'), folderId: $folderId));
}

it('keeps folder names unique among siblings, ignoring case and spaces, also at the top level', function () {
    // Arrange
    $campaigns = createFolder('Campaigns');
    createFolder('Summer', $campaigns);

    // Act
    createFolder('Summer');

    // Assert
    expect(fn () => createFolder(' summer ', $campaigns))->toThrow(MediaFolderNameTaken::class)
        ->and(fn () => createFolder('SUMMER'))->toThrow(MediaFolderNameTaken::class)
        ->and(MediaFolder::query()->where('name_key', 'summer')->count())->toBe(2);
});

it('lets the unique index refuse a duplicate the check did not see', function () {
    // Arrange
    createFolder('Summer');

    // Act & Assert
    expect(fn () => MediaFolder::query()->create(['name' => 'summer', 'name_key' => 'summer']))->toThrow(QueryException::class);
});

it('refuses names that are empty, too long, or have a slash or control character', function (string $name) {
    // Act & Assert
    expect(fn () => new MediaFolderName($name))->toThrow(InvalidMediaFolderName::class);
})->with(['empty' => '  ', 'too long' => str_repeat('a', 256), 'slash' => 'Summer/Winter', 'control character' => "Sum\tmer"]);

it('trims a name and accepts one of the longest length', function () {
    // Act
    $name = new MediaFolderName('  '.str_repeat('ä', 255).' ');

    // Assert
    expect(mb_strlen($name->value))->toBe(255);
});

it('creates a folder only in a parent that exists', function () {
    // Arrange
    $parent = createFolder('Campaigns');
    app(DeleteMediaFolder::class)->handle(MediaFolderId::of($parent));

    // Act & Assert
    expect(fn () => createFolder('Summer', $parent))->toThrow(MediaFolderNotFound::class);
});

it('renames a folder, also when only its case changes, but not to a sibling\'s name', function () {
    // Arrange
    $summer = createFolder('summer');
    createFolder('Winter');
    $rename = fn (string $name) => app(RenameMediaFolder::class)->handle(MediaFolderId::of($summer), new RenameMediaFolderPayload(new MediaFolderName($name)));

    // Act
    $rename('Summer');

    // Assert
    expect($summer->refresh()->name)->toBe('Summer')
        ->and(fn () => $rename('winter'))->toThrow(MediaFolderNameTaken::class);
});

it('moves a folder with its subfolders, and announces where from and to', function () {
    // Arrange
    Event::fake([MediaFolderMoved::class]);
    $campaigns = createFolder('Campaigns');
    $summer = createFolder('Summer');
    $beach = createFolder('Beach', $summer);

    // Act
    moveFolder($summer, $campaigns);

    // Assert
    expect($summer->refresh()->parent_id)->toBe($campaigns->id)
        ->and($beach->refresh()->parent_id)->toBe($summer->id);
    Event::assertDispatched(MediaFolderMoved::class, fn (MediaFolderMoved $event): bool => $event->oldParentId === null && $event->parentId?->value === $campaigns->id);
});

it('refuses to move a folder into itself or one of its subfolders, but allows a sibling branch', function () {
    // Arrange
    $root = createFolder('Root');
    $child = createFolder('Child', $root);
    $grandchild = createFolder('Grandchild', $child);
    $other = createFolder('Other');

    // Act
    moveFolder($other, $grandchild);

    // Assert
    expect(fn () => moveFolder($root, $grandchild))->toThrow(MediaFolderCycle::class)
        ->and(fn () => moveFolder($root, $root))->toThrow(MediaFolderCycle::class)
        ->and($root->refresh()->parent_id)->toBeNull()
        ->and($other->refresh()->parent_id)->toBe($grandchild->id);
});

it('refuses to move a folder next to one with the same name, and moves it back to the top level', function () {
    // Arrange
    $campaigns = createFolder('Campaigns');
    createFolder('Summer', $campaigns);
    $summer = createFolder('summer');
    $beach = createFolder('Beach', $campaigns);

    // Act
    moveFolder($beach, null);

    // Assert
    expect(fn () => moveFolder($summer, $campaigns))->toThrow(MediaFolderNameTaken::class)
        ->and($beach->refresh()->parent_id)->toBeNull();
});

it('deletes only empty folders', function () {
    // Arrange
    $withMedia = createFolder('With media');
    uploadInto(MediaFolderId::of($withMedia));
    $withFolder = createFolder('With folder');
    createFolder('Inner', $withFolder);
    $empty = createFolder('Empty');

    // Act
    app(DeleteMediaFolder::class)->handle(MediaFolderId::of($empty));

    // Assert
    expect(fn () => app(DeleteMediaFolder::class)->handle(MediaFolderId::of($withMedia)))->toThrow(MediaFolderNotEmpty::class)
        ->and(fn () => app(DeleteMediaFolder::class)->handle(MediaFolderId::of($withFolder)))->toThrow(MediaFolderNotEmpty::class)
        ->and(MediaFolder::query()->pluck('name')->sort()->values()->all())->toBe(['Inner', 'With folder', 'With media']);
});

it('uploads into a folder and says so', function () {
    // Arrange
    Event::fake([MediaUploaded::class]);
    $folder = createFolder('Campaigns');

    // Act
    $media = uploadInto(MediaFolderId::of($folder));

    // Assert
    expect($media->refresh()->folder_id)->toBe($folder->id);
    Event::assertDispatched(MediaUploaded::class, fn (MediaUploaded $event): bool => $event->folderId?->value === $folder->id);
});

it('refuses an upload into a deleted folder and removes the stored file', function () {
    // Arrange
    $folder = createFolder('Campaigns');
    app(DeleteMediaFolder::class)->handle(MediaFolderId::of($folder));

    // Act & Assert
    expect(fn () => uploadInto(MediaFolderId::of($folder)))->toThrow(MediaFolderNotFound::class)
        ->and(Media::query()->count())->toBe(0)
        ->and(Storage::disk('private-media')->allFiles())->toBe([]);
});

it('moves media, skips the deleted ones and says why, and announces only those whose folder changed', function () {
    // Arrange
    Event::fake([MediaMoved::class]);
    $folder = createFolder('Campaigns');
    $atTop = uploadInto(null);
    $alreadyThere = uploadInto(MediaFolderId::of($folder));
    $deleted = uploadInto(null);
    app(DeleteMedia::class)->handle(MediaId::of($deleted));

    // Act
    $result = app(MoveMedia::class)->handle(new MoveMediaPayload(
        [MediaId::of($deleted), MediaId::of($alreadyThere), MediaId::of($atTop)],
        MediaFolderId::of($folder),
    ));

    // Assert
    expect(array_map(fn (MediaId $id): int => $id->value, $result->moved))->toBe([$atTop->id, $alreadyThere->id])
        ->and($result->skipped)->toBe([$deleted->id => MediaMoveSkipReason::Deleted])
        ->and($atTop->refresh()->folder_id)->toBe($folder->id);
    Event::assertDispatched(MediaMoved::class, fn (MediaMoved $event): bool => array_map(fn (MediaId $id): int => $id->value, $event->mediaIds) === [$atTop->id]);
});

it('moves no media into a folder that was deleted', function () {
    // Arrange
    $folder = createFolder('Campaigns');
    $media = uploadInto(null);
    app(DeleteMediaFolder::class)->handle(MediaFolderId::of($folder));

    // Act & Assert
    expect(fn () => app(MoveMedia::class)->handle(new MoveMediaPayload([MediaId::of($media)], MediaFolderId::of($folder))))->toThrow(MediaFolderNotFound::class)
        ->and($media->refresh()->folder_id)->toBeNull();
});
