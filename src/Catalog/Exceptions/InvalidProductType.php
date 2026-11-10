<?php

namespace Larasell\Larasell\Catalog\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;

/** A product type was registered, extended or used in a way the registry does not allow. */
final class InvalidProductType extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    /** @param  list<string>  $known */
    public static function notRegistered(string $type, array $known): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.product-type-not-registered',
            message: "There is no product type [{$type}]; known are: ".implode(', ', $known).'.',
            why: 'Only registered types say which capabilities their products have.',
            fix: 'Use one of the known types, or register the type in ProductTypes first.',
        );
    }

    public static function alreadyRegistered(string $type): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.product-type-taken',
            message: "The product type [{$type}] is already registered.",
            why: 'A key names exactly one product type.',
            fix: 'Pick another key, or add capabilities to the existing type with ProductTypes::extend().',
        );
    }

    public static function unknownCapability(string $type, string $capability): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.unknown-product-capability',
            message: "The product type [{$type}] has the capability [{$capability}], which is not registered.",
            why: 'A capability only has an effect when the module owning its rules registered it.',
            fix: 'Register the capability in ProductCapabilities first, or remove it from the type.',
        );
    }

    public static function cannotRemoveCapabilities(string $type): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.product-type-extension-removes',
            message: "An extension of the product type [{$type}] removed capabilities or changed its key.",
            why: 'Other modules rely on the capabilities a type has, so extensions may only add to them.',
            fix: 'Return the given type with added capabilities, e.g. $type->withCapability(...).',
        );
    }

    public static function immutable(string $type): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.product-type-immutable',
            message: "The product type [{$type}] cannot be changed after the product or variant was created.",
            why: 'Stock, shipping and other data of the old type, and carts and open orders, would no longer match.',
            fix: 'Create a new product or variant of the other type instead.',
        );
    }
}
