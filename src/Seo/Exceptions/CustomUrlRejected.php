<?php

namespace Larasell\Larasell\Seo\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\SalesChannels\SalesContext;

/**
 * A custom URL attached to a catalog action could not be saved, so the action was undone. Names the
 * channel and locale it was for, e.g. to show the error on that channel's field.
 */
final class CustomUrlRejected extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public SalesContext $context;

    /** @var InvalidSeoPath::MALFORMED|InvalidSeoPath::RESERVED|InvalidSeoPath::TAKEN */
    public string $reason;

    public string $path;

    public static function in(SalesContext $context, InvalidSeoPath $previous): self
    {
        $exception = self::structured(
            errorCode: "larasell.seo.custom-url-{$previous->reason}",
            message: "The custom URL [{$previous->path}] of channel [{$context->channel}] in [{$context->locale->value}] was rejected: {$previous->getMessage()}",
            why: 'Custom URLs are saved with the catalog write, so the write is undone when one is rejected.',
            fix: 'Pick another path for that channel and locale; the previous exception says why this one failed.',
            previous: $previous,
        );
        $exception->context = $context;
        $exception->reason = $previous->reason;
        $exception->path = $previous->path;

        return $exception;
    }
}
