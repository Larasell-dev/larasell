<?php

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Media\Actions\DeleteMedia;
use Larasell\Larasell\Media\Actions\HandleMuxWebhook;
use Larasell\Larasell\Media\Actions\RegisterExternalMedia;
use Larasell\Larasell\Media\Actions\RegisterExternalMediaPayload;
use Larasell\Larasell\Media\Actions\UploadMedia;
use Larasell\Larasell\Media\Actions\UploadMediaPayload;
use Larasell\Larasell\Media\AssetStatus;
use Larasell\Larasell\Media\AssetVisibility;
use Larasell\Larasell\Media\Events\MediaAssetStatusChanged;
use Larasell\Larasell\Media\Events\MediaThumbnailGenerated;
use Larasell\Larasell\Media\Exceptions\AssetNotReady;
use Larasell\Larasell\Media\Exceptions\InvalidAssetStatusTransition;
use Larasell\Larasell\Media\Exceptions\UnknownMediaProvider;
use Larasell\Larasell\Media\Jobs\GenerateThumbnail;
use Larasell\Larasell\Media\Jobs\InspectMediaAsset;
use Larasell\Larasell\Media\MediaAssetId;
use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\MediaType;
use Larasell\Larasell\Media\Models\Media;
use Larasell\Larasell\Media\MuxWebhookSignature;

beforeEach(function () {
    Storage::fake('media');
    Storage::fake('private-media');
    Storage::fake('s3-public');
    Storage::fake('s3-private');
    config()->set('larasell.media.disks', ['public' => 'media', 'private' => 'private-media']);
    config()->set('larasell.media.providers.s3.disks', ['public' => 's3-public', 'private' => 's3-private']);
    config()->set('larasell.media.providers.mux', [
        'driver' => 'mux',
        'token_id' => 'token-id',
        'token_secret' => 'token-secret',
        'webhook_secret' => 'whsec',
    ]);
});

function fakeMuxUpload(): void
{
    Http::fake([
        'api.mux.com/video/v1/uploads' => Http::response(['data' => ['id' => 'upload-1', 'url' => 'https://storage.mux.test/upload-1']], 201),
        'storage.mux.test/*' => Http::response('', 200),
        // Only deletes; a test fakes what it reads.
        'api.mux.com/video/v1/assets/*' => fn (Request $request) => $request->method() === 'DELETE' ? Http::response('', 204) : null,
    ]);
}

function muxVideo(AssetVisibility $visibility = AssetVisibility::Public): Media
{
    fakeMuxUpload();

    return app(UploadMedia::class)->handle(new UploadMediaPayload(
        UploadedFile::fake()->create('unboxing.mp4', 100, 'video/mp4'),
        visibility: $visibility,
        provider: 'mux',
    ));
}

it('stores uploads with the default provider, ready at once, on the local disks', function () {
    // Act
    $media = app(UploadMedia::class)->handle(new UploadMediaPayload(UploadedFile::fake()->image('boots.png'), visibility: AssetVisibility::Public));

    // Assert
    expect($media->original->provider)->toBe('local')
        ->and($media->original->status)->toBe(AssetStatus::Ready)
        ->and($media->original->disk)->toBe('media');
});

it('stores uploads on the S3 provider\'s own disks when asked for it', function () {
    // Act
    $media = app(UploadMedia::class)->handle(new UploadMediaPayload(UploadedFile::fake()->image('boots.png'), visibility: AssetVisibility::Private, provider: 's3'));

    // Assert
    expect($media->original->provider)->toBe('s3')
        ->and($media->original->disk)->toBe('s3-private')
        ->and($media->original->status)->toBe(AssetStatus::Ready);
    Storage::disk('s3-private')->assertExists($media->original->path);
    Storage::disk('private-media')->assertMissing($media->original->path);
});

it('picks the provider configured for the media type', function () {
    // Arrange
    config()->set('larasell.media.provider_for.video', 's3');

    // Act
    $video = app(UploadMedia::class)->handle(new UploadMediaPayload(UploadedFile::fake()->create('unboxing.mp4', 100, 'video/mp4')));
    $image = app(UploadMedia::class)->handle(new UploadMediaPayload(UploadedFile::fake()->image('boots.png')));

    // Assert
    expect([$video->original->provider, $image->original->provider])->toBe(['s3', 'local']);
});

it('refuses a provider that is not configured', function () {
    // Act & Assert
    expect(fn () => app(UploadMedia::class)->handle(new UploadMediaPayload(UploadedFile::fake()->image('boots.png'), provider: 'dropbox')))
        ->toThrow(UnknownMediaProvider::class);
    expect(Media::query()->count())->toBe(0);
});

it('sends a video to Mux as a direct upload, processing until Mux says it is ready, with no thumbnail yet', function () {
    // Arrange
    Queue::fake();

    // Act
    $media = muxVideo(AssetVisibility::Private);

    // Assert
    expect($media->original->provider)->toBe('mux')
        ->and($media->original->status)->toBe(AssetStatus::Processing)
        ->and($media->original->path)->toBe('upload-1');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.mux.com/video/v1/uploads'
        && $request['new_asset_settings']['playback_policy'] === ['signed']
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('token-id:token-secret')));
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request->url() === 'https://storage.mux.test/upload-1');
    Queue::assertNotPushed(GenerateThumbnail::class);
});

it('has no URL for an asset until it is ready', function () {
    // Arrange
    $media = muxVideo();

    // Act & Assert
    expect(fn () => $media->original->url())->toThrow(AssetNotReady::class);
});

it('makes a Mux video ready from its webhook, with its playback URL, and ignores the same event sent again', function () {
    // Arrange
    $media = muxVideo();
    $ready = ['type' => 'video.asset.ready', 'data' => [
        'id' => 'asset-1', 'upload_id' => 'upload-1', 'status' => 'ready',
        'playback_ids' => [['id' => 'play-1', 'policy' => 'public']],
        'tracks' => [['type' => 'video', 'max_width' => 1920, 'max_height' => 1080]],
    ]];

    Event::fake([MediaAssetStatusChanged::class]);

    // Act
    app(HandleMuxWebhook::class)->handle($ready);
    app(HandleMuxWebhook::class)->handle($ready);

    // Assert
    $original = $media->original->fresh();
    expect($original->status)->toBe(AssetStatus::Ready)
        ->and($original->path)->toBe('asset-1')
        ->and([$original->width, $original->height])->toBe([1920, 1080])
        ->and($original->url())->toBe('https://stream.mux.com/play-1.m3u8');
    Event::assertDispatchedTimes(MediaAssetStatusChanged::class, 1);
    Event::assertDispatched(MediaAssetStatusChanged::class, fn (MediaAssetStatusChanged $event): bool => $event->status === AssetStatus::Ready && $event->mediaId->equals(MediaId::of($media)));
});

it('marks a Mux video failed, with Mux\'s reason, when it can\'t be processed', function () {
    // Arrange
    $media = muxVideo();

    // Act
    app(HandleMuxWebhook::class)->handle(['type' => 'video.asset.errored', 'data' => [
        'id' => 'asset-1', 'upload_id' => 'upload-1', 'status' => 'errored',
        'errors' => ['type' => 'invalid_input', 'messages' => ['The file is not a video.']],
    ]]);

    // Assert
    $original = $media->original->fresh();
    expect($original->status)->toBe(AssetStatus::Failed)
        ->and($original->failure)->toBe('The file is not a video.');
});

it('ignores Mux events for videos it doesn\'t know', function () {
    // Act & Assert
    app(HandleMuxWebhook::class)->handle(['type' => 'video.asset.ready', 'data' => ['id' => 'elsewhere', 'playback_ids' => []]]);
    expect(Media::query()->count())->toBe(0);
});

it('never turns a ready asset back, whatever comes in late', function () {
    // Arrange
    $media = muxVideo();
    app(HandleMuxWebhook::class)->handle(['type' => 'video.asset.ready', 'data' => ['id' => 'asset-1', 'upload_id' => 'upload-1', 'playback_ids' => [['id' => 'play-1', 'policy' => 'public']]]]);

    // Act
    app(HandleMuxWebhook::class)->handle(['type' => 'video.asset.errored', 'data' => ['id' => 'asset-1', 'errors' => ['messages' => ['late']]]]);

    // Assert
    expect($media->original->fresh()->status)->toBe(AssetStatus::Ready);
    expect(fn () => $media->original->fresh()->transitionTo(AssetStatus::Pending))->toThrow(InvalidAssetStatusTransition::class);
});

it('deletes a Mux video at Mux with its media', function () {
    // Arrange
    $media = muxVideo();
    app(HandleMuxWebhook::class)->handle(['type' => 'video.asset.ready', 'data' => ['id' => 'asset-1', 'upload_id' => 'upload-1', 'playback_ids' => [['id' => 'play-1', 'policy' => 'public']]]]);

    // Act
    app(DeleteMedia::class)->handle(MediaId::of($media));

    // Assert
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->url() === 'https://api.mux.com/video/v1/assets/asset-1');
});

it('accepts a Mux webhook only with a fresh signature made with the secret', function () {
    // Arrange
    $body = '{"type":"video.asset.ready"}';
    $now = 1_800_000_000;
    $sign = fn (int $at, string $secret): string => "t={$at},v1=".hash_hmac('sha256', "{$at}.{$body}", $secret);
    $signature = new MuxWebhookSignature('whsec');

    // Act & Assert
    expect($signature->valid($body, $sign($now, 'whsec'), $now))->toBeTrue()
        ->and($signature->valid($body, $sign($now, 'other'), $now))->toBeFalse()
        ->and($signature->valid($body, $sign($now - 600, 'whsec'), $now))->toBeFalse()
        ->and($signature->valid($body.' ', $sign($now, 'whsec'), $now))->toBeFalse()
        ->and($signature->valid($body, '', $now))->toBeFalse();
});

it('registers a file already on a provider\'s disk, pending until checked, then ready with what the check found', function () {
    // Arrange
    Storage::disk('s3-public')->put('catalog/boots.png', UploadedFile::fake()->image('boots.png', 30, 20)->getContent());

    // Act
    $media = app(RegisterExternalMedia::class)->handle(new RegisterExternalMediaPayload('s3', 'catalog/boots.png', MediaType::Image, 'boots.png', AssetVisibility::Public));

    // Assert
    $original = $media->original->fresh();
    expect($original->provider)->toBe('s3')
        ->and($original->status)->toBe(AssetStatus::Ready)
        ->and($original->mime_type)->toBe('image/png')
        ->and([$original->width, $original->height])->toBe([30, 20])
        ->and($original->size)->toBeGreaterThan(0);
    expect($media->thumbnail()->exists())->toBeTrue();
});

it('marks a registered file failed when the provider doesn\'t have it', function () {
    // Act
    $media = app(RegisterExternalMedia::class)->handle(new RegisterExternalMediaPayload('local', 'nowhere.png', MediaType::Image, 'nowhere.png', AssetVisibility::Public));

    // Assert
    expect($media->original->fresh()->status)->toBe(AssetStatus::Failed);
});

it('registers a video already at Mux, ready once Mux says so', function () {
    // Arrange
    Http::fake(['api.mux.com/video/v1/assets/asset-9' => Http::response(['data' => [
        'id' => 'asset-9', 'status' => 'ready',
        'playback_ids' => [['id' => 'play-9', 'policy' => 'public']],
        'tracks' => [['type' => 'video', 'max_width' => 640, 'max_height' => 360]],
    ]])]);

    // Act
    $media = app(RegisterExternalMedia::class)->handle(new RegisterExternalMediaPayload('mux', 'asset-9', MediaType::Video, 'launch.mp4', AssetVisibility::Public));

    // Assert
    $original = $media->original->fresh();
    expect($original->status)->toBe(AssetStatus::Ready)
        ->and($original->url())->toBe('https://stream.mux.com/play-9.m3u8')
        ->and([$original->width, $original->height])->toBe([640, 360]);
});

it('checks a video still uploading at Mux by its upload, e.g. when the webhook got lost', function () {
    // Arrange
    $media = muxVideo();
    Http::fake([
        'api.mux.com/video/v1/uploads/upload-1' => Http::response(['data' => ['id' => 'upload-1', 'status' => 'asset_created', 'asset_id' => 'asset-1']]),
        'api.mux.com/video/v1/assets/asset-1' => Http::response(['data' => [
            'id' => 'asset-1', 'status' => 'ready',
            'playback_ids' => [['id' => 'play-1', 'policy' => 'public']],
        ]]),
    ]);

    // Act
    InspectMediaAsset::dispatchSync(MediaAssetId::of($media->original));

    // Assert
    $original = $media->original->fresh();
    expect($original->status)->toBe(AssetStatus::Ready)
        ->and($original->path)->toBe('asset-1')
        ->and($original->url())->toBe('https://stream.mux.com/play-1.m3u8');
});

it('copies a frame of a ready Mux video into the image provider\'s storage, as its thumbnail', function () {
    // Arrange
    $media = muxVideo();
    Event::fake([MediaThumbnailGenerated::class]);
    Http::fake(['image.mux.com/*' => Http::response(UploadedFile::fake()->image('frame.png', 1280, 720)->getContent(), 200, ['Content-Type' => 'image/png'])]);

    // Act
    app(HandleMuxWebhook::class)->handle(['type' => 'video.asset.ready', 'data' => ['id' => 'asset-1', 'upload_id' => 'upload-1', 'playback_ids' => [['id' => 'play-1', 'policy' => 'public']]]]);

    // Assert
    $thumbnail = $media->thumbnail()->sole();
    expect($thumbnail->provider)->toBe('local')
        ->and($thumbnail->status)->toBe(AssetStatus::Ready)
        ->and($thumbnail->mime_type)->toBe('image/webp')
        ->and([$thumbnail->width, $thumbnail->height])->toBe([400, 225])
        ->and($thumbnail->disk)->toBe('media');
    Storage::disk('media')->assertExists($thumbnail->path);
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://image.mux.com/play-1/thumbnail.png'));
    Event::assertDispatched(MediaThumbnailGenerated::class);
});

it('fetches a private Mux video\'s frame with a token signed for thumbnails, and keeps it private', function () {
    // Arrange
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($key, $pem);
    config()->set('larasell.media.providers.mux.signing_key_id', 'key-1');
    config()->set('larasell.media.providers.mux.signing_key', base64_encode($pem));
    $media = muxVideo(AssetVisibility::Private);
    Http::fake(['image.mux.com/*' => Http::response(UploadedFile::fake()->image('frame.png', 640, 360)->getContent())]);

    // Act
    app(HandleMuxWebhook::class)->handle(['type' => 'video.asset.ready', 'data' => ['id' => 'asset-1', 'upload_id' => 'upload-1', 'playback_ids' => [['id' => 'play-1', 'policy' => 'signed']]]]);

    // Assert
    $thumbnail = $media->thumbnail()->sole();
    expect($thumbnail->visibility)->toBe(AssetVisibility::Private)
        ->and($thumbnail->disk)->toBe('private-media');
    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $claims = json_decode((string) base64_decode(strtr(explode('.', (string) ($query['token'] ?? ''))[1] ?? '', '-_', '+/')), true);

        return str_starts_with($request->url(), 'https://image.mux.com/play-1/thumbnail.png?token=')
            && ! isset($query['width'])
            && ($claims['aud'] ?? null) === 't'
            && ($claims['sub'] ?? null) === 'play-1';
    });
});
