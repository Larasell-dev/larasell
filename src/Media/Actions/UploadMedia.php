<?php

namespace Larasell\Larasell\Media\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Media\Contracts\MediaStorage;
use Larasell\Larasell\Media\Events\MediaUploaded;
use Larasell\Larasell\Media\Exceptions\UnsupportedMedia;
use Larasell\Larasell\Media\Jobs\GenerateThumbnail;
use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\MediaType;
use Larasell\Larasell\Media\Models\Media;
use Larasell\Larasell\Media\Models\MediaAsset;
use Throwable;

/** Stores an image or video and adds it to the media library, with the file as its original asset. */
final readonly class UploadMedia
{
    public function __construct(private ConnectionInterface $db, private MediaStorage $storage) {}

    /**
     * @throws UnsupportedMedia when the file is not an image or video of a supported type
     */
    public function handle(UploadMediaPayload $payload): Media
    {
        $file = $payload->file;
        // Detected from the file's content where possible, not taken from the browser.
        $mimeType = (string) $file->getMimeType();
        $type = MediaType::forMimeType($mimeType) ?? throw UnsupportedMedia::mimeType($file->getClientOriginalName(), $mimeType);
        $dimensions = $type === MediaType::Image ? @getimagesize($file->getRealPath()) : false;
        $stored = $this->storage->store($file, $payload->visibility);

        try {
            return $this->db->transaction(function () use ($payload, $file, $mimeType, $type, $dimensions, $stored): Media {
                $media = Media::query()->create(['type' => $type, 'title' => $payload->title, 'description' => $payload->description, 'alt' => $payload->alt]);
                $media->setRelation('original', $media->assets()->create([
                    'role' => MediaAsset::ORIGINAL,
                    'visibility' => $payload->visibility,
                    'disk' => $stored->disk,
                    'path' => $stored->path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $mimeType,
                    'size' => (int) $file->getSize(),
                    'width' => $dimensions === false ? null : $dimensions[0],
                    'height' => $dimensions === false ? null : $dimensions[1],
                    'checksum' => (string) hash_file('sha256', $file->getRealPath()),
                ]));

                MediaUploaded::dispatch(MediaId::of($media), $payload->attachments);

                // Once committed, so the worker finds the media.
                GenerateThumbnail::dispatch(MediaId::of($media))->afterCommit();

                return $media;
            });
        } catch (Throwable $exception) {
            $this->storage->delete($stored);

            throw $exception;
        }
    }
}
