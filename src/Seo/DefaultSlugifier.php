<?php

namespace Larasell\Larasell\Seo;

use Illuminate\Support\Str;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Seo\Contracts\Slugifier;
use Larasell\Larasell\Translatable;

/** Laravel's Str::slug with the locale's transliteration rules, so "Größe" becomes "groesse" in German. */
final class DefaultSlugifier implements Slugifier
{
    public function slug(Translatable $text, Locale $locale): ?string
    {
        $translations = $text->all();

        foreach (Locale::lookupChain($locale->value) as $candidate) {
            if (isset($translations[$candidate])) {
                $slug = Str::slug($translations[$candidate], '-', explode('-', $locale->value)[0]);

                return $slug === '' ? null : $slug;
            }
        }

        return null;
    }
}
