<?php

use Larasell\Larasell\Enums\Locale;

return [
    /*
     * The locales the shop serves. Use Locale cases, or BCP 47 tags such as "de-DE" when the
     * value comes from the environment.
     */
    'supported' => [Locale::ENGLISH],

    /*
     * The locale for requests that match no supported locale, and the translation fields fall
     * back to when they have no text in the requested locale. It must be one of the supported locales.
     */
    'default' => Locale::ENGLISH,
];
