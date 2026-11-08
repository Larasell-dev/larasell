<?php

namespace Larasell\Larasell\Media\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Media\MediaFolderName;
use RuntimeException;

final class InvalidMediaFolderName extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function empty(): self
    {
        return self::structured(
            errorCode: 'larasell.media.folder-name-empty',
            message: 'A media folder needs a name.',
            why: 'The name is empty, or only spaces.',
            fix: 'Give the folder a name.',
        );
    }

    public static function tooLong(string $name): self
    {
        $max = MediaFolderName::MAX_LENGTH;

        return self::structured(
            errorCode: 'larasell.media.folder-name-too-long',
            message: "The media folder name [{$name}] is longer than {$max} characters.",
            fix: "Shorten the name to {$max} characters or fewer.",
        );
    }

    public static function forbiddenCharacter(string $name): self
    {
        return self::structured(
            errorCode: 'larasell.media.folder-name-forbidden-character',
            message: "The media folder name [{$name}] has a slash or a control character.",
            why: 'Slashes are kept for folder paths, and control characters are invisible.',
            fix: 'Remove the slash or control character from the name.',
        );
    }

    public static function notUtf8(): self
    {
        return self::structured(
            errorCode: 'larasell.media.folder-name-not-utf8',
            message: 'The media folder name is not valid UTF-8.',
            fix: 'Send the name as UTF-8.',
        );
    }
}
