<?php

namespace Larasell\Larasell\Catalog\Brands\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Catalog\Brands\BrandId;
use RuntimeException;

/** Products use the brand, so it can't be deleted. */
final class BrandInUse extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    /** The products that use it. */
    public private(set) int $products = 0;

    public static function byProducts(BrandId $brandId, int $products): self
    {
        $exception = self::structured(
            errorCode: 'larasell.catalog.brand-in-use',
            message: "Brand [{$brandId}] can't be deleted while {$products} products use it.",
            why: 'Those products would lose their brand.',
            fix: 'Give those products another brand, or none, first.',
        );
        $exception->products = $products;

        return $exception;
    }
}
