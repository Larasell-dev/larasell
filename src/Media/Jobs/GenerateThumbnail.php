<?php

namespace Larasell\Larasell\Media\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Process;
use Larasell\Larasell\Media\AssetStatus;
use Larasell\Larasell\Media\Contracts\ProvidesPosters;
use Larasell\Larasell\Media\Events\MediaThumbnailGenerated;
use Larasell\Larasell\Media\Exceptions\MediaNotProcessed;
use Larasell\Larasell\Media\Exceptions\MediaNotShared;
use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\MediaProviders;
use Larasell\Larasell\Media\MediaType;
use Larasell\Larasell\Media\Models\Media;
use Larasell\Larasell\Media\Models\MediaAsset;
use Symfony\Component\Process\Exception\ProcessStartFailedException;

/**
 * Makes a small WebP for lists and grids, of an image or of a frame FFmpeg takes from a video, as its "thumbnail" asset, just as visible as
 * the original. For a video at a provider that keeps its bytes, e.g. Mux, the frame is the
 * provider's poster, and the thumbnail goes to the provider images go to. Queued on larasell.media.thumbnails.queue, so workers, on this machine or on
 * others that share its disks, do the resizing instead of the web server.
 */
final class GenerateThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly MediaId $mediaId)
    {
        $this->onQueue(config('larasell.media.thumbnails.queue'));
    }

    /**
     * @throws MediaNotShared when this worker can't read the original
     * @throws MediaNotProcessed when FFmpeg can't run on this worker
     */
    public function handle(MediaProviders $providers): void
    {
        $media = Media::query()->with('original')->find($this->mediaId->value);

        // Deleted before the job ran.
        if ($media === null) {
            return;
        }

        $original = $media->original;
        $source = $providers->provider($original->provider);
        $size = (int) config('larasell.media.thumbnails.size');

        if ($original->status !== AssetStatus::Ready) {
            return;
        }

        if ($source->readable()) {
            $stream = $source->readStream($original->file()) ?? throw MediaNotShared::original((string) $original->disk, $original->path);
            $contents = (string) stream_get_contents($stream);
            fclose($stream);
            $frame = $media->type === MediaType::Video ? $this->videoFrame($contents) : $contents;
            $storage = $source;
        } elseif ($source instanceof ProvidesPosters) {
            // A frame the provider made, e.g. Mux, kept where images go, as the provider keeps none.
            $frame = $source->poster($original->file(), $size);
            $storage = $providers->provider($providers->nameFor(MediaType::Image));
        } else {
            return;
        }

        if (! $storage->readable()) {
            return;
        }

        // A file it can't decode gets no thumbnail; the grid shows a placeholder instead.
        $image = $frame === null ? false : @imagecreatefromstring($frame);

        if ($image === false) {
            return;
        }

        [$width, $height] = [imagesx($image), imagesy($image)];
        $scale = min(1, $size / max($width, $height));
        $thumbnail = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagedestroy($image);

        if ($thumbnail === false) {
            return;
        }

        $temporary = (string) tempnam(sys_get_temp_dir(), 'larasell-thumbnail');
        imagewebp($thumbnail, $temporary, 80);

        try {
            $stored = $storage->store(new UploadedFile($temporary, 'thumbnail.webp', 'image/webp', null, true), $original->visibility);
            $previous = $media->assets()->where('role', MediaAsset::THUMBNAIL)->first();
            $media->assets()->updateOrCreate(['role' => MediaAsset::THUMBNAIL], [
                'visibility' => $original->visibility,
                'provider' => $stored->provider,
                'status' => $stored->status,
                'disk' => $stored->disk,
                'path' => $stored->path,
                'file_name' => pathinfo($original->file_name, PATHINFO_FILENAME).'.webp',
                'mime_type' => 'image/webp',
                'size' => (int) filesize($temporary),
                'width' => imagesx($thumbnail),
                'height' => imagesy($thumbnail),
                'checksum' => (string) hash_file('sha256', $temporary),
            ]);

            if ($previous !== null) {
                $storage->delete($previous->file());
            }

            MediaThumbnailGenerated::dispatch($this->mediaId);
        } finally {
            imagedestroy($thumbnail);
            @unlink($temporary);
        }
    }

    /**
     * A PNG of a telling frame near the start, which FFmpeg picks from the first 100 so a fade
     * in from black doesn't become the poster.
     *
     * @throws MediaNotProcessed when FFmpeg can't run on this worker
     */
    private function videoFrame(string $video): ?string
    {
        $input = (string) tempnam(sys_get_temp_dir(), 'larasell-video');
        $output = $input.'.png';
        file_put_contents($input, $video);

        try {
            $binary = (string) config('larasell.media.ffmpeg');

            try {
                $result = Process::run([$binary, '-loglevel', 'error', '-y', '-i', $input, '-vf', 'thumbnail', '-frames:v', '1', $output]);
            } catch (ProcessStartFailedException $exception) {
                throw MediaNotProcessed::ffmpegMissing($binary, $exception->getMessage());
            }

            // 127: the shell found no such command.
            if ($result->exitCode() === 127) {
                throw MediaNotProcessed::ffmpegMissing($binary, trim($result->errorOutput()));
            }

            return $result->successful() && is_file($output) ? (string) file_get_contents($output) : null;
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }
}
