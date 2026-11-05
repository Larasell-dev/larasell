<?php

namespace Larasell\Larasell\Seo;

use Larasell\Larasell\Seo\Contracts\CollisionPolicy;

/** Automatic paths get "-2", "-3", …; a custom path an admin entered fails instead. */
final class SuffixCollisionPolicy implements CollisionPolicy
{
    public function alternative(string $path, int $attempt, bool $custom): ?string
    {
        return $custom ? null : $path.'-'.($attempt + 1);
    }
}
