<?php

namespace Larasell\Larasell\Media\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use RuntimeException;

final class MediaNotStored extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function onDisk(string $disk, string $fileName): self
    {
        return self::structured(
            errorCode: 'larasell.media.not-stored',
            message: "The file [{$fileName}] could not be written to the disk [{$disk}].",
            why: 'The disk refused the write, e.g. because it is full or its credentials are wrong.',
            fix: 'Check the disk in larasell.media.providers or larasell.media.disks and its configuration in config/filesystems.php.',
        );
    }

    public static function atProvider(string $provider, string $fileName, string $reason): self
    {
        return self::structured(
            errorCode: 'larasell.media.not-stored-at-provider',
            message: "The file [{$fileName}] could not be sent to the media provider [{$provider}]: {$reason}",
            why: 'The provider refused the request or could not be reached, e.g. because its credentials are wrong.',
            fix: "Check larasell.media.providers.{$provider} and the provider's status.",
        );
    }
}
