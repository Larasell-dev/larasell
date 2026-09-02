<?php

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case German = 'de';
    case French = 'fr';
    case Spanish = 'es';
    case Italian = 'it';
    case Dutch = 'nl';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::German => 'Deutsch',
            self::French => 'Français',
            self::Spanish => 'Español',
            self::Italian => 'Italiano',
            self::Dutch => 'Nederlands',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function enabled(): array
    {
        return [
            self::English,
            self::German,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function enabledValues(): array
    {
        return array_map(
            fn (self $locale): string => $locale->value,
            self::enabled(),
        );
    }
}
