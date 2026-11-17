<?php

namespace Larasell\Larasell\Catalog\Manufacturers\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Catalog\Manufacturers\ManufacturerId;
use RuntimeException;

/** Products use the manufacturer, so it can't be deleted. */
final class ManufacturerInUse extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    /** The products that use it. */
    public private(set) int $products = 0;

    public static function byProducts(ManufacturerId $manufacturerId, int $products): self
    {
        $exception = self::structured(
            errorCode: 'larasell.catalog.manufacturer-in-use',
            message: "Manufacturer [{$manufacturerId}] can't be deleted while {$products} products use it.",
            why: 'Those products would lose their manufacturer.',
            fix: 'Give those products another manufacturer, or none, first.',
        );
        $exception->products = $products;

        return $exception;
    }
}
