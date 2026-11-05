<?php

namespace Larasell\Larasell\Catalog\Exceptions;

use RuntimeException;

/**
 * Another module still needs the product, so it cannot be deleted. Thrown by listeners of
 * ProductDeleted inside the delete's transaction, which undoes the delete.
 */
final class ProductInUse extends RuntimeException
{
    /** @param  list<int>  $orderIds */
    public static function openOrders(int $productId, array $orderIds): self
    {
        return new self("Product [{$productId}] cannot be deleted while it is part of open order [".implode(', ', $orderIds).'].');
    }

    public static function reservedStock(int $productId, int $quantity): self
    {
        return new self("Product [{$productId}] cannot be deleted while {$quantity} reserved units of it await their order.");
    }
}
