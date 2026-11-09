<?php

namespace Larasell\Larasell\Media;

use DateTimeInterface;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Larasell\Larasell\Media\Contracts\MediaStorage;
use Larasell\Larasell\Media\Contracts\ProvidesPosters;
use Larasell\Larasell\Media\Exceptions\AssetNotReady;
use Larasell\Larasell\Media\Exceptions\MediaNotStored;
use Larasell\Larasell\Media\Exceptions\UnknownMediaProvider;
use Larasell\Larasell\Media\Exceptions\UnsupportedMedia;

/**
 * The mux driver: videos at Mux, which transcodes them and streams them as HLS. An upload goes
 * straight to Mux and is processing until Mux's webhook says it is ready (HandleMuxWebhook).
 * Public videos play by a public playback ID; private ones by a signed one, with URLs signed by
 * the provider's signing key. Mux holds no bytes Larasell can read; the video's thumbnail is a
 * frame Mux makes (poster()), copied to where images go.
 *
 * Its reference is the Mux upload ID until Mux made the asset, then the asset ID.
 *
 * @phpstan-type MuxAsset array{id?: string, upload_id?: string, asset_id?: string, status?: string, playback_ids?: list<array{id: string, policy: string}>, tracks?: list<array<string, mixed>>, errors?: array{messages?: list<string>}}
 */
final readonly class MuxMediaStorage implements MediaStorage, ProvidesPosters
{
    private const API = 'https://api.mux.com';

    /** @param  array<string, mixed>  $config */
    public function __construct(private Factory $http, private string $name, private array $config) {}

    public function store(UploadedFile $file, AssetVisibility $visibility): StoredFile
    {
        $mimeType = (string) $file->getMimeType();

        if (MediaType::forMimeType($mimeType) !== MediaType::Video) {
            throw UnsupportedMedia::byProvider($this->name, $file->getClientOriginalName(), $mimeType);
        }

        try {
            $upload = $this->api()->post('/video/v1/uploads', [
                'cors_origin' => '*',
                'new_asset_settings' => ['playback_policy' => [self::policy($visibility)]],
            ])->throw()->json('data');

            $this->http->withBody(Utils::streamFor(fopen($file->getRealPath(), 'r')), $mimeType)
                ->put($upload['url'])->throw();
        } catch (RequestException $exception) {
            throw MediaNotStored::atProvider($this->name, $file->getClientOriginalName(), $exception->getMessage());
        }

        return new StoredFile($this->name, null, $upload['id'], AssetStatus::Processing, ['upload_id' => $upload['id']]);
    }

    public function locate(string $reference, AssetVisibility $visibility): StoredFile
    {
        return new StoredFile($this->name, null, $reference, AssetStatus::Pending, ['policy' => self::policy($visibility)]);
    }

    public function inspect(StoredFile $file): AssetInspection
    {
        $assetId = $file->path;

        // Still known by its upload, e.g. when Mux's webhook got lost: the upload names its asset.
        if ($file->path === ($file->metadata['upload_id'] ?? null)) {
            $upload = $this->api()->get("/video/v1/uploads/{$file->path}")->throw()->json('data');

            if (in_array($upload['status'] ?? null, ['errored', 'cancelled', 'timed_out'], true)) {
                return AssetInspection::failed('The upload to Mux did not finish.');
            }

            if (! isset($upload['asset_id'])) {
                return new AssetInspection(AssetStatus::Processing);
            }

            $assetId = (string) $upload['asset_id'];
        }

        $response = $this->api()->get("/video/v1/assets/{$assetId}");

        if ($response->notFound()) {
            return AssetInspection::failed("Mux has no asset [{$assetId}].");
        }

        /** @var MuxAsset $asset */
        $asset = $response->throw()->json('data');

        return self::inspection($asset);
    }

    /**
     * What a Mux asset, from its API or a webhook, says about the file.
     *
     * @param  MuxAsset  $asset
     */
    public static function inspection(array $asset): AssetInspection
    {
        $video = collect($asset['tracks'] ?? [])->firstWhere('type', 'video');
        $playback = $asset['playback_ids'][0] ?? null;

        return match ($asset['status'] ?? 'ready') {
            'errored' => AssetInspection::failed(implode(' ', $asset['errors']['messages'] ?? ['Mux could not process the video.'])),
            'preparing' => new AssetInspection(AssetStatus::Processing, path: $asset['id'] ?? null),
            default => new AssetInspection(
                AssetStatus::Ready,
                path: $asset['id'] ?? null,
                width: isset($video['max_width']) ? (int) $video['max_width'] : null,
                height: isset($video['max_height']) ? (int) $video['max_height'] : null,
                metadata: $playback === null ? [] : ['playback_id' => $playback['id'], 'policy' => $playback['policy']],
            ),
        };
    }

    /** Mux plays the same asset differently instead: a new playback ID replaces the old one. */
    public function copy(StoredFile $file, AssetVisibility $visibility): StoredFile
    {
        if ($file->status !== AssetStatus::Ready) {
            throw AssetNotReady::forUrl($file->path, $file->status);
        }

        $policy = self::policy($visibility);
        $playback = $this->api()->post("/video/v1/assets/{$file->path}/playback-ids", ['policy' => $policy])->throw()->json('data');

        if (isset($file->metadata['playback_id'])) {
            $this->api()->delete("/video/v1/assets/{$file->path}/playback-ids/{$file->metadata['playback_id']}");
        }

        return new StoredFile($this->name, null, $file->path, AssetStatus::Ready, ['playback_id' => $playback['id'], 'policy' => $policy] + $file->metadata);
    }

    public function delete(StoredFile $file): void
    {
        // Still an upload: cancel it, so Mux makes no asset of it.
        $this->api()->send(...($file->path === ($file->metadata['upload_id'] ?? null)
            ? ['PUT', "/video/v1/uploads/{$file->path}/cancel"]
            : ['DELETE', "/video/v1/assets/{$file->path}"]));
    }

    public function url(StoredFile $file): string
    {
        return "https://stream.mux.com/{$file->metadata['playback_id']}.m3u8";
    }

    /** A JWT signed with the signing key, as Mux wants for signed playback IDs. */
    public function temporaryUrl(StoredFile $file, DateTimeInterface $expiresAt): string
    {
        if (($file->metadata['policy'] ?? 'public') === 'public') {
            return $this->url($file);
        }

        return $this->url($file).'?token='.$this->token((string) $file->metadata['playback_id'], 'v', $expiresAt->getTimestamp());
    }

    /**
     * The frame Mux picks, as a PNG. A signed playback ID's image needs a token for thumbnails,
     * which must carry the width too, instead of the query.
     */
    public function poster(StoredFile $file, int $width): ?string
    {
        $playbackId = $file->metadata['playback_id'] ?? null;

        if (! is_string($playbackId)) {
            return null;
        }

        $url = "https://image.mux.com/{$playbackId}/thumbnail.png";
        $response = ($file->metadata['policy'] ?? 'public') === 'public'
            ? $this->http->get($url, ['width' => $width])
            : $this->http->get($url, ['token' => $this->token($playbackId, 't', time() + 300, ['width' => $width])]);

        return $response->successful() ? $response->body() : null;
    }

    /** @param  array<string, mixed>  $claims */
    private function token(string $playbackId, string $audience, int $expiresAt, array $claims = []): string
    {
        $keyId = (string) ($this->config['signing_key_id'] ?? '') ?: throw UnknownMediaProvider::notConfigured($this->name, 'signing_key_id');
        $key = (string) ($this->config['signing_key'] ?? '') ?: throw UnknownMediaProvider::notConfigured($this->name, 'signing_key');
        // Mux hands out the private key base64-encoded; a PEM works too.
        $pem = str_contains($key, 'BEGIN') ? $key : (string) base64_decode($key, true);
        $encode = fn (string $part): string => rtrim(strtr(base64_encode($part), '+/', '-_'), '=');
        $unsigned = $encode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $keyId])).'.'
            .$encode((string) json_encode(['sub' => $playbackId, 'aud' => $audience, 'exp' => $expiresAt] + $claims));
        openssl_sign($unsigned, $signature, $pem, OPENSSL_ALGO_SHA256);

        return $unsigned.'.'.$encode($signature);
    }

    public function readable(): bool
    {
        return false;
    }

    public function readStream(StoredFile $file)
    {
        return null;
    }

    public function webhookSignature(): MuxWebhookSignature
    {
        return new MuxWebhookSignature((string) ($this->config['webhook_secret'] ?? ''));
    }

    private function api(): PendingRequest
    {
        return $this->http->baseUrl(self::API)->acceptJson()->withBasicAuth(
            (string) ($this->config['token_id'] ?? '') ?: throw UnknownMediaProvider::notConfigured($this->name, 'token_id'),
            (string) ($this->config['token_secret'] ?? '') ?: throw UnknownMediaProvider::notConfigured($this->name, 'token_secret'),
        );
    }

    private static function policy(AssetVisibility $visibility): string
    {
        return $visibility === AssetVisibility::Public ? 'public' : 'signed';
    }
}
