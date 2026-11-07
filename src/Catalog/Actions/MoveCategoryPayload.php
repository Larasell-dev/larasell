<?php

namespace Larasell\Larasell\Catalog\Actions;

use Larasell\Larasell\Catalog\CategoryId;

/** Where a category moves: below its new parent, or to the top level with null. */
final readonly class MoveCategoryPayload
{
    public function __construct(
        public ?CategoryId $parentId,
        /** Its place among its new siblings from 0; null or past the last puts it last. */
        public ?int $position = null,
    ) {}
}
