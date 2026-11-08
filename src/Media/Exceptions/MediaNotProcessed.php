<?php

namespace Larasell\Larasell\Media\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use RuntimeException;

/** A queued media job couldn't turn a stored file into what it makes, such as a video's poster. */
final class MediaNotProcessed extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function ffmpegMissing(string $binary, string $error): self
    {
        return self::structured(
            errorCode: 'larasell.media.ffmpeg-missing',
            message: "The queue worker can't run FFmpeg [{$binary}]: {$error}",
            why: 'Video thumbnails are frames FFmpeg takes from the video, and it is not installed where this worker runs.',
            fix: 'Install FFmpeg where the queue worker runs, or point larasell.media.ffmpeg at its binary.',
        );
    }
}
