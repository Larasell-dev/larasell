<?php

namespace Larasell\Larasell\Seo\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** No page has ever had the path (404). */
class SeoPageNotFound extends NotFoundHttpException
{
    public function __construct(public readonly string $path)
    {
        parent::__construct("No page at [{$path}].");
    }
}
