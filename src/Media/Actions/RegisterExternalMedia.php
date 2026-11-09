<?php

namespace Larasell\Larasell\Media\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Media\Events\MediaUploaded;
use Larasell\Larasell\Media\Exceptions\MediaFolderNotFound;
use Larasell\Larasell\Media\Exceptions\UnknownMediaProvider;
use Larasell\Larasell\Media\Jobs\InspectMediaAsset;
use Larasell\Larasell\Media\MediaAssetId;
use Larasell\Larasell\Media\MediaFolders;
use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\MediaProviders;
use Larasell\Larasell\Media\Models\Media;
use Larasell\Larasell\Media\Models\MediaAsset;

/**
 * Adds a file a provider already has to the media library, e.g. a video at Mux or a file on S3.
 * Its original is pending until InspectMediaAsset checked it with the provider, which makes it
 * ready, with its MIME type, size and dimensions, or failed.
 */
final readonly class RegisterExternalMedia
{
    public function __construct(private ConnectionInterface $db, private MediaProviders $providers, private MediaFolders $folders) {}

    /**
     * @throws UnknownMediaProvider when the provider isn't configured
     * @throws MediaFolderNotFound when the folder doesn't exist (any more)
     */
    public function handle(RegisterExternalMediaPayload $payload): Media
    {
        $stored = $this->providers->provider($payload->provider)->locate($payload->reference, $payload->visibility);

        return $this->db->transaction(function () use ($payload, $stored): Media {
            if ($payload->folderId !== null) {
                $this->folders->lockTarget($payload->folderId);
            }

            $media = Media::query()->create(['folder_id' => $payload->folderId?->value, 'type' => $payload->type, 'title' => $payload->title, 'description' => $payload->description, 'alt' => $payload->alt]);
            $original = $media->assets()->create([
                'role' => MediaAsset::ORIGINAL,
                'visibility' => $payload->visibility,
                'provider' => $stored->provider,
                'status' => $stored->status,
                'disk' => $stored->disk,
                'path' => $stored->path,
                'metadata' => $stored->metadata ?: null,
                'file_name' => $payload->fileName,
                // Until the inspection says what it is.
                'mime_type' => 'application/octet-stream',
                'size' => 0,
                'checksum' => '',
            ]);
            $media->setRelation('original', $original);

            MediaUploaded::dispatch(MediaId::of($media), $payload->attachments, $payload->folderId);
            InspectMediaAsset::dispatch(MediaAssetId::of($original))->afterCommit();

            return $media;
        });
    }
}
