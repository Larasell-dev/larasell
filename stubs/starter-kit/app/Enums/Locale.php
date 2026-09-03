<?php

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case German = 'de';

    /** @return list<self> */
    public static function enabled(): array
    {
        return [
            self::English,
            self::German,
        ];
    }
}
