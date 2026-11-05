<?php

namespace Larasell\Larasell\Seo\Contracts;

use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Translatable;

interface Slugifier
{
    /**
     * One path segment from the text's translation in the locale, or null when it has none.
     * A regional locale may use its language ("de-AT" uses "de"), but never another language.
     */
    public function slug(Translatable $text, Locale $locale): ?string;
}
