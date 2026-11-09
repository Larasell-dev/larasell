<?php

namespace Larasell\Larasell\Media\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Larasell\Larasell\Media\AssetInspection;
use Larasell\Larasell\Media\AssetStatus;
use Larasell\Larasell\Media\AssetVisibility;
use Larasell\Larasell\Media\Contracts\MediaStorage;
use Larasell\Larasell\Media\Contracts\ProvidesPosters;
use Larasell\Larasell\Media\Events\MediaAssetStatusChanged;
use Larasell\Larasell\Media\Exceptions\AssetNotReady;
use Larasell\Larasell\Media\Exceptions\InvalidAssetStatusTransition;
use Larasell\Larasell\Media\Exceptions\PrivateMedia;
use Larasell\Larasell\Media\Jobs\GenerateThumbnail;
use Larasell\Larasell\Media\MediaAssetId;
use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\MediaProviders;
use Larasell\Larasell\Media\StoredFile;

/**
 * One file of a media item, kept by a media provider (MediaStorage).
 *
 * @property int $id
 * @property int $media_id
 * @property string $role "original" for the file as uploaded, "thumbnail" for its small WebP
 * @property AssetVisibility $visibility
 * @property string $provider its name in larasell.media.providers
 * @property AssetStatus $status
 * @property string|null $failure why it failed, when it did
 * @property string|null $disk for providers built on Laravel disks
 * @property string $path the provider's reference: a path, or e.g. a Mux asset ID
 * @property array<string, mixed>|null $metadata the provider's own, e.g. a Mux playback ID
 * @property string $file_name the name it was uploaded with
 * @property string $mime_type
 * @property int $size in bytes
 * @property int|null $width in pixels; null for videos
 * @property int|null $height in pixels; null for videos
 * @property string $checksum SHA-256 of the file; empty when the provider doesn't hand it out
 */
class MediaAsset extends Model
{
    public const ORIGINAL = 'original';

    public const THUMBNAIL = 'thumbnail';

    protected $table = 'larasell_media_assets';

    protected $guarded = [];

    protected $casts = [
        'visibility' => AssetVisibility::class,
        'status' => AssetStatus::class,
        'metadata' => 'array',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function file(): StoredFile
    {
        return new StoredFile($this->provider, $this->disk, $this->path, $this->status, $this->metadata ?? []);
    }

    /**
     * Moves it to the next status, e.g. from a job that retries it.
     *
     * @throws InvalidAssetStatusTransition when the status can't follow its current one
     */
    public function transitionTo(AssetStatus $status, ?string $failure = null): void
    {
        if (! $this->status->canBecome($status)) {
            throw InvalidAssetStatusTransition::between(MediaAssetId::of($this), $this->status, $status);
        }

        $this->update(['status' => $status, 'failure' => $status === AssetStatus::Failed ? $failure : null]);
        MediaAssetStatusChanged::dispatch(new MediaId($this->media_id), MediaAssetId::of($this), $status);
    }

    /**
     * Takes what its provider found out, by checking it or from a webhook. Providers send events
     * late, twice or out of order, so one that can't follow the current status is left out
     * instead of thrown. An original that turns ready gets its thumbnail, where the provider
     * hands out its bytes or a poster.
     *
     * @return bool whether it took it
     */
    public function record(AssetInspection $inspection): bool
    {
        $moves = $inspection->status !== $this->status;

        if ($moves && ! $this->status->canBecome($inspection->status)) {
            return false;
        }

        if (! $moves && $this->status === AssetStatus::Ready) {
            return false;
        }

        $this->update(array_filter([
            'path' => $inspection->path,
            'mime_type' => $inspection->mimeType,
            'size' => $inspection->size,
            'width' => $inspection->width,
            'height' => $inspection->height,
            'checksum' => $inspection->checksum,
        ], fn (mixed $value): bool => $value !== null) + [
            'status' => $inspection->status,
            'failure' => $inspection->failure,
            'metadata' => $inspection->metadata + ($this->metadata ?? []),
        ]);

        if ($moves) {
            MediaAssetStatusChanged::dispatch(new MediaId($this->media_id), MediaAssetId::of($this), $inspection->status);
        }

        $provider = app(MediaProviders::class)->provider($this->provider);

        if ($moves && $inspection->status === AssetStatus::Ready && $this->role === self::ORIGINAL
            && ($provider->readable() || $provider instanceof ProvidesPosters)) {
            GenerateThumbnail::dispatch(new MediaId($this->media_id))->afterCommit();
        }

        return true;
    }

    /**
     * The file's lasting URL.
     *
     * @throws PrivateMedia for a private file, which only has a URL that expires
     * @throws AssetNotReady until its provider is done with it
     */
    public function url(): string
    {
        if ($this->status !== AssetStatus::Ready) {
            throw AssetNotReady::forUrl($this->file_name, $this->status);
        }

        if ($this->visibility === AssetVisibility::Private) {
            throw PrivateMedia::hasNoLastingUrl($this->file_name);
        }

        return app(MediaStorage::class)->url($this->file());
    }

    /** A URL that stops working at $expiresAt; hand it out only once the requester may see the file. */
    public function temporaryUrl(DateTimeInterface $expiresAt): string
    {
        if ($this->status !== AssetStatus::Ready) {
            throw AssetNotReady::forUrl($this->file_name, $this->status);
        }

        return app(MediaStorage::class)->temporaryUrl($this->file(), $expiresAt);
    }
}
