<?php

namespace Larasell\Larasell\Catalog\Exceptions;

use RuntimeException;

/** A category cannot move below itself or one of its descendants. */
final class CategoryCycle extends RuntimeException
{
    public function __construct(public readonly int $categoryId, public readonly int $parentId)
    {
        parent::__construct("Category [{$categoryId}] cannot move below category [{$parentId}], which is itself or one of its descendants.");
    }
}
