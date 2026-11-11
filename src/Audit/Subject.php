<?php

namespace Larasell\Larasell\Audit;

/**
 * What an audited action was done to. The type is a stable name such as api_token rather than a
 * class name, so records stay searchable when classes move.
 *
 * @public
 */
final readonly class Subject
{
    public string $id;

    public function __construct(public string $type, int|string $id)
    {
        $this->id = (string) $id;
    }
}
