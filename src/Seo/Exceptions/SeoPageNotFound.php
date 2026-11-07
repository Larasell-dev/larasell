<?php

namespace Larasell\Larasell\Seo\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredFields;
use Larasell\Chronicle\Exceptions\StructuredException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** No page has ever had the path (404). */
class SeoPageNotFound extends NotFoundHttpException implements StructuredException
{
    use HasStructuredFields;

    public function __construct(public readonly string $path)
    {
        parent::__construct("No page at [{$path}].");

        $this->withError('larasell.seo.page-not-found', 'No page has ever had this path.', 'Check the link, or give a page this path.');
    }
}
