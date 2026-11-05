<?php

namespace Larasell\Larasell\Seo;

enum ResolutionKind
{
    /** The path is the canonical URL of an available page. */
    case Found;

    /** The path is an old URL, or a deleted page's URL with a target. */
    case Redirect;

    /** The page exists but is not available, e.g. unpublished. */
    case Hidden;

    /** The page was deleted and its URL leads nowhere. */
    case Gone;

    /** No page has ever had this path. */
    case NotFound;
}
