<?php

namespace Larasell\Larasell\Seo\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\SalesChannels\SalesContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The page exists but is not available in the context, e.g. unpublished (404). */
class SeoPageHidden extends NotFoundHttpException
{
    public function __construct(public readonly Model $resource, public readonly SalesContext $context)
    {
        parent::__construct('The page is not available.');
    }
}
