<?php

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Larasell\Larasell\Media\Actions\UpdateMuxSettings;
use Larasell\Larasell\Media\Actions\UpdateMuxSettingsPayload;
use Larasell\Larasell\Media\Actions\UploadMedia;
use Larasell\Larasell\Media\Actions\UploadMediaPayload;
use Larasell\Larasell\Media\AssetVisibility;
use Larasell\Larasell\Media\Events\MuxSettingsUpdated;
use Larasell\Larasell\Media\MediaProviders;
use Larasell\Larasell\Media\MediaType;
use Larasell\Larasell\Media\MuxSettings;
use Larasell\Larasell\Models\Setting;

beforeEach(function () {
    Storage::fake('media');
    Storage::fake('private-media');
    config()->set('larasell.media.disks', ['public' => 'media', 'private' => 'private-media']);
    config()->set('larasell.media.provider_for.video', null);
    config()->set('larasell.media.providers.mux', [
        'driver' => 'mux',
        'token_id' => 'config-id',
        'token_secret' => 'config-secret',
        'webhook_secret' => 'config-whsec',
    ]);
});

function muxPayload(array $overrides = []): UpdateMuxSettingsPayload
{
    return new UpdateMuxSettingsPayload(...array_merge([
        'tokenId' => 'saved-id',
        'tokenSecret' => 'saved-secret',
        'webhookSecret' => 'saved-whsec',
        'signingKeyId' => 'key-id',
        'signingKey' => 'private-key',
        'useForVideos' => true,
    ], $overrides));
}

it('uploads videos to Mux with the saved credentials instead of the config file', function () {
    // Arrange
    app(UpdateMuxSettings::class)->handle(muxPayload());
    Http::fake([
        'api.mux.com/video/v1/uploads' => Http::response(['data' => ['id' => 'upload-1', 'url' => 'https://storage.mux.test/upload-1']], 201),
        'storage.mux.test/*' => Http::response('', 200),
    ]);

    // Act
    $media = app(UploadMedia::class)->handle(new UploadMediaPayload(
        UploadedFile::fake()->create('unboxing.mp4', 100, 'video/mp4'),
        visibility: AssetVisibility::Public,
    ));

    // Assert
    expect($media->original->provider)->toBe('mux');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.mux.com/video/v1/uploads'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('saved-id:saved-secret')));
});

it('keeps a saved secret when the payload leaves it out, and never stores secrets readable', function () {
    // Arrange
    app(UpdateMuxSettings::class)->handle(muxPayload());

    // Act
    app(UpdateMuxSettings::class)->handle(muxPayload(['tokenId' => 'new-id', 'tokenSecret' => null, 'webhookSecret' => null, 'signingKey' => null]));

    // Assert
    $settings = app(MuxSettings::class);
    expect($settings->tokenId())->toBe('new-id')
        ->and($settings->has('token_secret'))->toBeTrue()
        ->and(app(MediaProviders::class)->config('mux'))->toMatchArray([
            'token_id' => 'new-id',
            'token_secret' => 'saved-secret',
            'webhook_secret' => 'saved-whsec',
            'signing_key' => 'private-key',
        ]);
    $stored = json_encode(Setting::query()->where('key', MuxSettings::KEY)->value('value'));
    expect($stored)->not->toContain('saved-secret')->not->toContain('saved-whsec')->not->toContain('private-key');
});

it('falls back to the config file for what was never saved', function () {
    // Act
    $config = app(MediaProviders::class)->config('mux');

    // Assert
    expect($config)->toMatchArray(['token_id' => 'config-id', 'token_secret' => 'config-secret'])
        ->and(app(MuxSettings::class)->has('token_secret'))->toBeFalse()
        ->and(app(MediaProviders::class)->nameFor(MediaType::Video))->toBe('local');
});

it('sends videos to Mux only while it is turned on for them', function () {
    // Arrange
    app(UpdateMuxSettings::class)->handle(muxPayload());
    $before = app(MediaProviders::class)->nameFor(MediaType::Video);

    // Act
    app(UpdateMuxSettings::class)->handle(muxPayload(['useForVideos' => false]));

    // Assert
    expect($before)->toBe('mux')
        ->and(app(MediaProviders::class)->nameFor(MediaType::Video))->toBe('local')
        ->and(app(MediaProviders::class)->nameFor(MediaType::Image))->toBe('local');
});

it('announces the change without the secrets', function () {
    // Arrange
    Event::fake([MuxSettingsUpdated::class]);

    // Act
    app(UpdateMuxSettings::class)->handle(muxPayload());

    // Assert
    Event::assertDispatched(MuxSettingsUpdated::class, fn (MuxSettingsUpdated $event) => ! str_contains(serialize($event), 'saved-secret'));
});
