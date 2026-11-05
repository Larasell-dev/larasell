<?php

namespace Larasell\Larasell\Catalog\Fixtures;

use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\ProductId;

function compareIds(ProductId $a, ?ProductId $b, CategoryId $c, int $number): void
{
    $same = $a === $b;
    $different = $a !== $c;
    $unset = $b === null;
    $unsetToo = $b !== null;
    $byValue = $a->value === $number;
    $byEquals = $a->equals($c);
}
