<?php

namespace Larasell\Larasell\RichText;

use JsonException;
use Larasell\Larasell\Exceptions\InvalidRichText;

/**
 * Formatted text as Portable Text (https://portabletext.org), limited to what storefronts can
 * render safely:
 *
 *     [
 *         {"_type": "block", "_key": "b0", "style": "h2", "markDefs": [],
 *          "children": [{"_type": "span", "_key": "b0s0", "text": "Care", "marks": []}]},
 *         {"_type": "block", "_key": "b1", "style": "normal", "listItem": "bullet", "level": 1,
 *          "markDefs": [{"_type": "link", "_key": "b1l0", "href": "https://…"}],
 *          "children": [{"_type": "span", "_key": "b1s0", "text": "No dryer", "marks": ["strong", "b1l0"]}]}
 *     ]
 *
 * Styles normal, h2, h3 and h4; flat bullet and number lists; the decorators strong, em and
 * underline; and link annotations to absolute http, https and mailto addresses. It stores no
 * HTML: toHtml() builds it and escapes every text.
 *
 * @phpstan-type Span array{_type: 'span', _key: string, text: string, marks?: list<string>}
 * @phpstan-type Link array{_type: 'link', _key: string, href: string}
 * @phpstan-type Block array{_type: 'block', _key: string, style: string, listItem?: string, level?: int, markDefs?: list<Link>, children: list<Span>}
 */
final readonly class RichText
{
    public const array STYLES = ['normal', 'h2', 'h3', 'h4'];

    public const array LIST_ITEMS = ['bullet', 'number'];

    /** The decorators, with the tag each renders as, in the order they nest. */
    private const array DECORATORS = ['strong' => 'strong', 'em' => 'em', 'underline' => 'u'];

    /**
     * @param  list<Block>  $blocks
     */
    private function __construct(private array $blocks) {}

    /**
     * @throws InvalidRichText
     */
    public static function fromArray(mixed $blocks): self
    {
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            throw InvalidRichText::at('document', 'must be a list of blocks.');
        }

        foreach ($blocks as $index => $block) {
            self::block($block, (string) $index);
        }

        /** @var list<Block> $blocks */
        return new self($blocks);
    }

    /**
     * @throws InvalidRichText
     */
    public static function fromJson(string $json): self
    {
        try {
            return self::fromArray(json_decode($json, true, 64, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw InvalidRichText::at('document', 'is not JSON.');
        }
    }

    /**
     * Reads a stored text, which may still be plain text from before descriptions were
     * formatted: that becomes one paragraph per line.
     */
    public static function fromStored(string $stored): self
    {
        $decoded = json_decode($stored, true);

        if (is_array($decoded) && array_is_list($decoded)) {
            return self::fromArray($decoded);
        }

        $lines = array_values(array_filter(array_map(trim(...), preg_split('/\R/', $stored) ?: []), fn (string $line): bool => $line !== ''));

        return new self(array_map(fn (string $line, int $index): array => [
            '_type' => 'block',
            '_key' => "b{$index}",
            'style' => 'normal',
            'markDefs' => [],
            'children' => [['_type' => 'span', '_key' => "b{$index}s0", 'text' => $line, 'marks' => []]],
        ], $lines, array_keys($lines)));
    }

    /**
     * @return list<Block>
     */
    public function toArray(): array
    {
        return $this->blocks;
    }

    public function toJson(): string
    {
        return json_encode($this->blocks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function isEmpty(): bool
    {
        foreach ($this->blocks as $block) {
            if (implode('', array_column($block['children'], 'text')) !== '') {
                return false;
            }
        }

        return true;
    }

    /** One line per block. */
    public function toPlainText(): string
    {
        return implode("\n", array_map(
            fn (array $block): string => implode('', array_column($block['children'], 'text')),
            $this->blocks,
        ));
    }

    /**
     * HTML that's safe to show as it is. Neighboring list items of one kind form one list.
     */
    public function toHtml(): string
    {
        $html = '';
        $list = null;

        foreach ($this->blocks as $block) {
            $item = $block['listItem'] ?? null;

            if ($item !== $list) {
                $html .= match ($list) {
                    'bullet' => '</ul>', 'number' => '</ol>', default => ''
                };
                $html .= match ($item) {
                    'bullet' => '<ul>', 'number' => '<ol>', default => ''
                };
                $list = $item;
            }

            $tag = $item !== null ? 'li' : ($block['style'] === 'normal' ? 'p' : $block['style']);
            $html .= "<{$tag}>".self::inline($block)."</{$tag}>";
        }

        return $html.match ($list) {
            'bullet' => '</ul>', 'number' => '</ol>', default => ''
        };
    }

    /**
     * @param  Block  $block
     */
    private static function inline(array $block): string
    {
        $links = array_column($block['markDefs'] ?? [], 'href', '_key');
        $html = '';

        foreach ($block['children'] as $span) {
            $text = e($span['text']);
            $marks = $span['marks'] ?? [];

            foreach (array_reverse(self::DECORATORS) as $decorator => $tag) {
                if (in_array($decorator, $marks, true)) {
                    $text = "<{$tag}>{$text}</{$tag}>";
                }
            }

            foreach ($marks as $mark) {
                if (isset($links[$mark])) {
                    $text = '<a href="'.e($links[$mark]).'">'.$text.'</a>';
                }
            }

            $html .= $text;
        }

        return $html;
    }

    private static function block(mixed $block, string $path): void
    {
        self::object($block, $path, ['_type', '_key', 'style', 'listItem', 'level', 'markDefs', 'children']);
        /** @var array<string, mixed> $block */
        self::type($block, $path, 'block');

        if (! in_array($block['style'] ?? null, self::STYLES, true)) {
            throw InvalidRichText::at("{$path}.style", 'must be normal, h2, h3 or h4.');
        }

        if (array_key_exists('listItem', $block)) {
            if (! in_array($block['listItem'], self::LIST_ITEMS, true)) {
                throw InvalidRichText::at("{$path}.listItem", 'must be bullet or number.');
            }

            if ($block['style'] !== 'normal' || ($block['level'] ?? 1) !== 1) {
                throw InvalidRichText::at($path, 'list items must be normal text at level 1.');
            }
        } elseif (array_key_exists('level', $block)) {
            throw InvalidRichText::at("{$path}.level", 'is only allowed on list items.');
        }

        $links = [];

        foreach (self::list($block['markDefs'] ?? [], "{$path}.markDefs") as $index => $link) {
            self::object($link, "{$path}.markDefs.{$index}", ['_type', '_key', 'href']);
            /** @var array<string, mixed> $link */
            self::type($link, "{$path}.markDefs.{$index}", 'link');

            if (! is_string($link['href'] ?? null) || ! preg_match('#^(https?://\S+|mailto:\S+)$#i', $link['href'])) {
                throw InvalidRichText::at("{$path}.markDefs.{$index}.href", 'must be an absolute http, https or mailto address.');
            }

            $links[] = $link['_key'];
        }

        foreach (self::list($block['children'] ?? null, "{$path}.children") as $index => $span) {
            self::object($span, "{$path}.children.{$index}", ['_type', '_key', 'text', 'marks']);
            /** @var array<string, mixed> $span */
            self::type($span, "{$path}.children.{$index}", 'span');

            if (! is_string($span['text'] ?? null)) {
                throw InvalidRichText::at("{$path}.children.{$index}.text", 'must be a string.');
            }

            $marks = self::list($span['marks'] ?? [], "{$path}.children.{$index}.marks");

            if (array_diff($marks, array_keys(self::DECORATORS), $links) !== []) {
                throw InvalidRichText::at("{$path}.children.{$index}.marks", 'may only be strong, em, underline and the block\'s links.');
            }
        }
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidRichText::at($path, 'must be a list.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private static function type(array $value, string $path, string $type): void
    {
        if (($value['_type'] ?? null) !== $type) {
            throw InvalidRichText::at("{$path}._type", "must be {$type}.");
        }

        if (! is_string($value['_key'] ?? null) || $value['_key'] === '') {
            throw InvalidRichText::at("{$path}._key", 'must be a non-empty string.');
        }
    }

    /**
     * @param  list<string>  $keys
     */
    private static function object(mixed $value, string $path, array $keys): void
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw InvalidRichText::at($path, 'must be an object.');
        }

        $unknown = array_diff(array_keys($value), $keys);

        if ($unknown !== []) {
            throw InvalidRichText::at($path, 'has unknown keys '.implode(', ', $unknown).'.');
        }
    }
}
