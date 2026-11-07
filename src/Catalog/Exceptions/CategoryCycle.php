<?php

namespace Larasell\Larasell\Catalog\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredFields;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Catalog\CategoryId;
use RuntimeException;

/** A category cannot move below itself or one of its descendants. */
final class CategoryCycle extends RuntimeException implements StructuredException
{
    use HasStructuredFields;

    public function __construct(public readonly CategoryId $categoryId, public readonly CategoryId $parentId)
    {
        parent::__construct("Category [{$categoryId}] cannot move below category [{$parentId}], which is itself or one of its descendants.");

        $this->withError(
            'larasell.catalog.category-cycle',
            'The category tree would loop back on itself.',
            'Move the category below a category outside its own branch, or move the descendant out first.',
        );
    }
}
