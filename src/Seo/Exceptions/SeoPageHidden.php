<?php

namespace Larasell\Larasell\Seo\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Larasell\Chronicle\Exceptions\HasStructuredFields;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Storefront\StorefrontContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The page exists but is not available in the context, e.g. unpublished (404). */
class SeoPageHidden extends NotFoundHttpException implements StructuredException
{
    use HasStructuredFields;

    public function __construct(public readonly Model $resource, public readonly StorefrontContext $context)
    {
        parent::__construct('The page is not available.');

        $this->withError('larasell.seo.page-hidden', 'The page exists but is not available in this storefront context, e.g. unpublished.', 'Publish the page in the context, or register a HiddenPagePolicy that redirects it.');
    }
}
