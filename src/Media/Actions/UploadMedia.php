<?php

namespace Larasell\Larasell\Media\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Media\AssetStatus;
use Larasell\Larasell\Media\Events\MediaUploaded;
use Larasell\Larasell\Media\Exceptions\MediaFolderNotFound;
use Larasell\Larasell\Media\Exceptions\UnknownMediaProvider;
use Larasell\Larasell\Media\Exceptions\UnsupportedMedia;
use Larasell\Larasell\Media\Jobs\GenerateThumbnail;
use Larasell\Larasell\Media\MediaFolders;
use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\MediaProviders;
use Larasell\Larasell\Media\MediaType;
use Larasell\Larasell\Media\Models\Media;
use Larasell\Larasell\Media\Models\MediaAsset;
use Throwable;

/** Stores an image or video and adds it to the media library, with the file as its original asset. */
final readonly class UploadMedia
{
    public function __construct(private ConnectionInterface $db, private MediaProviders $providers, private MediaFolders $folders) {}

    /**
     * @throws UnsupportedMedia when the file is not an image or video of a supported type
     * @throws MediaFolderNotFound when the folder doesn't exist (any more); the stored file is removed
     * @throws UnknownMediaProvider when the payload's provider isn't configured
     */
    public function handle(UploadMediaPayload $payload): Media
    {
        $file = $payload->file;
        // Detected from the file's content where possible, not taken from the browser.
        $mimeType = (string) $file->getMimeType();
        $type = MediaType::forMimeType($mimeType) ?? throw UnsupportedMedia::mimeType($file->getClientOriginalName(), $mimeType);
        $dimensions = $type === MediaType::Image ? @getimagesize($file->getRealPath()) : false;
        $provider = $this->providers->provider($payload->provider ?? $this->providers->nameFor($type));
        $stored = $provider->store($file, $payload->visibility);

        try {
            return $this->db->transaction(function () use ($payload, $file, $mimeType, $type, $dimensions, $stored, $provider): Media {
                // Checked here, not before storing: only the lock keeps the folder from going until commit.
                if ($payload->folderId !== null) {
                    $this->folders->lockTarget($payload->folderId);
                }

                $media = Media::query()->create(['folder_id' => $payload->folderId?->value, 'type' => $type, 'title' => $payload->title, 'description' => $payload->description, 'alt' => $payload->alt]);
                $media->setRelation('original', $media->assets()->create([
                    'role' => MediaAsset::ORIGINAL,
                    'visibility' => $payload->visibility,
                    'provider' => $stored->provider,
                    'status' => $stored->status,
                    'disk' => $stored->disk,
                    'path' => $stored->path,
                    'metadata' => $stored->metadata ?: null,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $mimeType,
                    'size' => (int) $file->getSize(),
                    'width' => $dimensions === false ? null : $dimensions[0],
                    'height' => $dimensions === false ? null : $dimensions[1],
                    'checksum' => (string) hash_file('sha256', $file->getRealPath()),
                ]));

                MediaUploaded::dispatch(MediaId::of($media), $payload->attachments, $payload->folderId);

                // Once committed, so the worker finds the media. A provider still working on the file
                // makes it ready later, which queues the thumbnail then (MediaAsset::record()).
                if ($stored->status === AssetStatus::Ready && $provider->readable()) {
                    GenerateThumbnail::dispatch(MediaId::of($media))->afterCommit();
                }

                return $media;
            });
        } catch (Throwable $exception) {
            $provider->delete($stored);

            throw $exception;
        }
    }
}
