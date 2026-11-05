<?php

namespace Larasell\Larasell\Seo;

use Larasell\Larasell\Seo\Contracts\PathNormalizer;
use Normalizer;

/** Unicode NFC, lowercase, no repeated, leading, or trailing slashes. */
final class DefaultPathNormalizer implements PathNormalizer
{
    public function normalize(string $path): string
    {
        $path = Normalizer::normalize($path, Normalizer::FORM_C) ?: $path;

        return trim((string) preg_replace('#/+#', '/', mb_strtolower($path)), '/');
    }
}
