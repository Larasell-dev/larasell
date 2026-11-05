<?php

namespace Larasell\Larasell\PHPStan;

/**
 * Which module a class belongs to, from the namespace prefixes in phpstan.neon (larasell.modules).
 * The longest matching prefix wins. Classes outside every module belong to none, and the rules
 * leave them alone.
 */
final readonly class ModuleMap
{
    /** @param  array<string, list<string>>  $modules  Module name => namespace or class name prefixes */
    public function __construct(private array $modules) {}

    public function moduleOf(?string $class): ?string
    {
        if ($class === null) {
            return null;
        }

        $class = ltrim($class, '\\');
        $match = null;
        $length = 0;

        foreach ($this->modules as $module => $prefixes) {
            foreach ($prefixes as $prefix) {
                $prefix = ltrim($prefix, '\\');

                if (($class === rtrim($prefix, '\\') || str_starts_with($class, $prefix)) && strlen($prefix) > $length) {
                    $match = $module;
                    $length = strlen($prefix);
                }
            }
        }

        return $match;
    }
}
