<?php

namespace Larasell\Larasell\Media\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use RuntimeException;

/**
 * A queued media job couldn't read a file the app stored. Workers on another server only see files
 * on a disk both share, such as S3; a local disk exists on one machine only.
 */
final class MediaNotShared extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function original(string $disk, string $path): self
    {
        return self::structured(
            errorCode: 'larasell.media.not-shared',
            message: "The queue worker can't read [{$path}] on the disk [{$disk}].",
            why: 'The file is missing on this worker, which happens when the worker runs on another server and the disk is local to the web server.',
            fix: 'Run the worker on the same machine as the app, or point larasell.media.disks at disks both reach, e.g. S3 or R2.',
        );
    }
}
