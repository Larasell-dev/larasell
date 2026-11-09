<?php

namespace Larasell\Larasell\Media;

use Larasell\Larasell\Media\Exceptions\InvalidMediaFolderName;
use Normalizer;

/**
 * A folder's name: plain text, the same in every locale. Trimmed and in NFC; its key is what two
 * names are compared by, so "Summer" and "summer" can't sit side by side.
 */
final readonly class MediaFolderName
{
    public const MAX_LENGTH = 255;

    public string $value;

    public string $key;

    /**
     * @throws InvalidMediaFolderName when the name is empty, too long, or has a slash or control character
     */
    public function __construct(string $name)
    {
        $value = Normalizer::normalize(trim($name), Normalizer::NFC);

        if ($value === false || preg_match('//u', $value) !== 1) {
            throw InvalidMediaFolderName::notUtf8();
        }

        if ($value === '') {
            throw InvalidMediaFolderName::empty();
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidMediaFolderName::tooLong($value);
        }

        // Slashes are kept free for paths such as "Campaigns/Summer".
        if (preg_match('~[/\p{Cc}]~u', $value) === 1) {
            throw InvalidMediaFolderName::forbiddenCharacter($value);
        }

        $this->value = $value;
        $this->key = mb_strtolower($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
