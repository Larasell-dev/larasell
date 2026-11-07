<?php

namespace Larasell\Larasell;

use Larasell\Larasell\Exceptions\DuplicateExtension;

/**
 * What an app passes along with an action for its own listeners, one object per class, e.g. the
 * custom URLs an admin form typed. The action hands it to its event untouched; the core never
 * reads it.
 */
final readonly class Extensions
{
    /** @var array<class-string, object> */
    private array $items;

    /**
     * @throws DuplicateExtension when two objects share a class
     */
    public function __construct(object ...$items)
    {
        $byClass = [];

        foreach ($items as $item) {
            if (isset($byClass[$item::class])) {
                throw DuplicateExtension::of($item::class);
            }

            $byClass[$item::class] = $item;
        }

        $this->items = $byClass;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function get(string $class): ?object
    {
        /** @var T|null */
        return $this->items[$class] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
