<?php

namespace Larasell\Larasell\Seo\Contracts;

interface CollisionPolicy
{
    /**
     * The path to try when the path is taken or reserved, for attempts 1, 2, …,
     * or null to fail the save with an InvalidSeoPath exception.
     */
    public function alternative(string $path, int $attempt, bool $custom): ?string;
}
