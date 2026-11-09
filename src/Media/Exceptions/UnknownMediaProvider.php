<?php

namespace Larasell\Larasell\Media\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use LogicException;

final class UnknownMediaProvider extends LogicException implements StructuredException
{
    use HasStructuredException;

    public static function named(string $name): self
    {
        return self::structured(
            errorCode: 'larasell.media.unknown-provider',
            message: "There is no media provider [{$name}].",
            why: 'Only providers listed in larasell.media.providers can keep files.',
            fix: "Add [{$name}] to larasell.media.providers, or use one listed there.",
        );
    }

    public static function driver(string $name, string $driver): self
    {
        return self::structured(
            errorCode: 'larasell.media.unknown-provider-driver',
            message: "The media provider [{$name}] uses the driver [{$driver}], which doesn't exist.",
            why: 'Larasell knows the drivers local, s3 and mux, and those added with MediaProviders::extend().',
            fix: "Use one of those as larasell.media.providers.{$name}.driver.",
        );
    }

    public static function notConfigured(string $name, string $key): self
    {
        return self::structured(
            errorCode: 'larasell.media.provider-not-configured',
            message: "The media provider [{$name}] needs larasell.media.providers.{$name}.{$key}.",
            why: 'The provider can\'t reach its service without it.',
            fix: "Set larasell.media.providers.{$name}.{$key}, usually from an environment variable.",
        );
    }
}
