<?php

return [
    /*
     * The locales the shop serves, as BCP 47 language tags such as "en", "de-DE" or "zh-Hans".
     * Each must be a value of Larasell\Larasell\Enums\Locale.
     */
    'supported' => ['en'],

    /*
     * The locale for requests that match no supported locale, and the translation fields fall
     * back to when they have no text in the requested locale. It must be one of the supported locales.
     */
    'default' => 'en',
];
