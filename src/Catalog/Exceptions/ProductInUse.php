<?php

namespace Larasell\Larasell\Catalog\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Catalog\ProductId;
use RuntimeException;

/**
 * Another module still needs the product, so it cannot be deleted. Thrown by listeners of
 * ProductDeleted inside the delete's transaction, which undoes the delete.
 */
final class ProductInUse extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    /** @param  list<int>  $orderIds  Plain numbers: orders are downstream of the catalog, which cannot name OrderId */
    public static function openOrders(ProductId $productId, array $orderIds): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.product-in-open-orders',
            message: "Product [{$productId}] cannot be deleted while it is part of open order [".implode(', ', $orderIds).'].',
            why: 'Open orders still have to be fulfilled with the product.',
            fix: 'Complete or cancel the orders first, or deactivate the product instead.',
        );
    }

    public static function reservedStock(ProductId $productId, int $quantity): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.product-stock-reserved',
            message: "Product [{$productId}] cannot be deleted while {$quantity} reserved units of it await their order.",
            why: 'Checkouts in progress have reserved stock of the product.',
            fix: 'Wait until the reservations are released or ordered, or deactivate the product instead.',
        );
    }
}
