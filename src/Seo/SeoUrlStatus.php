<?php

namespace Larasell\Larasell\Seo;

enum SeoUrlStatus: string
{
    /** The resource's current URL in its context. */
    case Canonical = 'canonical';

    /** An old URL of a resource that still exists; it redirects to the canonical one. */
    case Historic = 'historic';

    /** A URL of a deleted resource, kept as a tombstone. */
    case Gone = 'gone';
}
