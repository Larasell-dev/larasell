<?php

namespace Larasell\Larasell\RichText;

use JsonException;
use Larasell\Larasell\Exceptions\InvalidRichText;

/**
 * Formatted text in Larasell's own JSON format, independent of the editor that writes it:
 *
 *     {"version": 1, "blocks": [
 *         {"type": "heading", "level": 2, "children": [{"text": "Care"}]},
 *         {"type": "paragraph", "children": [{"text": "Wash ", "marks": ["bold"], "link": {"href": "https://…"}}]},
 *         {"type": "list", "style": "bullet", "items": [[{"text": "No dryer"}]]}
 *     ]}
 *
 * Only these blocks, the marks bold, italic and underline, and absolute http, https and mailto
 * links are allowed. It stores no HTML: toHtml() builds it and escapes every text.
 *
 * @phpstan-type Span array{text: string, marks?: list<string>, link?: array{href: string}}
 * @phpstan-type Block array{type: 'paragraph', children: list<Span>}|array{type: 'heading', level: int, children: list<Span>}|array{type: 'list', style: string, items: list<list<Span>>}
 * @phpstan-type Document array{version: int, blocks: list<Block>}
 */
final readonly class RichText
{
    public const int VERSION = 1;

    public const array MARKS = ['bold', 'italic', 'underline'];

    private const array TAGS = ['bold' => 'strong', 'italic' => 'em', 'underline' => 'u'];

    /**
     * @param  Document  $document
     */
    private function __construct(private array $document) {}

    /**
     * @throws InvalidRichText
     */
    public static function fromArray(mixed $document): self
    {
        self::object($document, 'document', ['version', 'blocks']);

        if (($document['version'] ?? null) !== self::VERSION) {
            throw InvalidRichText::at('version', 'only version '.self::VERSION.' is supported.');
        }

        if (! is_array($document['blocks'] ?? null) || ! array_is_list($document['blocks'])) {
            throw InvalidRichText::at('blocks', 'must be a list.');
        }

        foreach ($document['blocks'] as $index => $block) {
            self::block($block, "blocks.{$index}");
        }

        /** @var Document $document */
        return new self($document);
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

        if (is_array($decoded) && array_key_exists('version', $decoded)) {
            return self::fromArray($decoded);
        }

        $lines = array_filter(array_map(trim(...), preg_split('/\R/', $stored) ?: []), fn (string $line): bool => $line !== '');

        return new self(['version' => self::VERSION, 'blocks' => array_map(
            fn (string $line): array => ['type' => 'paragraph', 'children' => [['text' => $line]]],
            array_values($lines),
        )]);
    }

    /**
     * @return Document
     */
    public function toArray(): array
    {
        return $this->document;
    }

    public function toJson(): string
    {
        return json_encode($this->document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function isEmpty(): bool
    {
        return implode('', array_map(
            fn (array $line): string => implode('', array_column($line, 'text')),
            $this->lines(),
        )) === '';
    }

    public function toPlainText(): string
    {
        return implode("\n", array_map(
            fn (array $line): string => implode('', array_column($line, 'text')),
            $this->lines(),
        ));
    }

    /**
     * HTML that's safe to show as it is.
     */
    public function toHtml(): string
    {
        $html = '';

        foreach ($this->document['blocks'] as $block) {
            $html .= match ($block['type']) {
                'paragraph' => '<p>'.self::inline($block['children']).'</p>',
                'heading' => "<h{$block['level']}>".self::inline($block['children'])."</h{$block['level']}>",
                'list' => ($block['style'] === 'bullet' ? '<ul>' : '<ol>')
                    .implode('', array_map(fn (array $item): string => '<li>'.self::inline($item).'</li>', $block['items']))
                    .($block['style'] === 'bullet' ? '</ul>' : '</ol>'),
            };
        }

        return $html;
    }

    /**
     * Every paragraph, heading and list item's spans, in order.
     *
     * @return list<list<Span>>
     */
    private function lines(): array
    {
        $lines = [];

        foreach ($this->document['blocks'] as $block) {
            array_push($lines, ...($block['type'] === 'list' ? $block['items'] : [$block['children']]));
        }

        return $lines;
    }

    /**
     * @param  list<Span>  $spans
     */
    private static function inline(array $spans): string
    {
        $html = '';

        foreach ($spans as $span) {
            $text = e($span['text']);
            $marks = $span['marks'] ?? [];

            // In a fixed order, so the same marks always nest the same way.
            foreach (array_reverse(self::TAGS) as $mark => $tag) {
                if (in_array($mark, $marks, true)) {
                    $text = "<{$tag}>{$text}</{$tag}>";
                }
            }

            if (isset($span['link'])) {
                $text = '<a href="'.e($span['link']['href']).'">'.$text.'</a>';
            }

            $html .= $text;
        }

        return $html;
    }

    private static function block(mixed $block, string $path): void
    {
        $type = is_array($block) ? ($block['type'] ?? null) : null;

        match ($type) {
            'paragraph' => self::object($block, $path, ['type', 'children']),
            'heading' => self::object($block, $path, ['type', 'level', 'children']),
            'list' => self::object($block, $path, ['type', 'style', 'items']),
            default => throw InvalidRichText::at("{$path}.type", 'must be paragraph, heading or list.'),
        };

        if ($type === 'heading' && ! in_array($block['level'] ?? null, [2, 3, 4], true)) {
            throw InvalidRichText::at("{$path}.level", 'must be 2, 3 or 4.');
        }

        if ($type === 'list') {
            if (! in_array($block['style'] ?? null, ['bullet', 'numbered'], true)) {
                throw InvalidRichText::at("{$path}.style", 'must be bullet or numbered.');
            }

            if (! is_array($block['items'] ?? null) || ! array_is_list($block['items'])) {
                throw InvalidRichText::at("{$path}.items", 'must be a list.');
            }

            foreach ($block['items'] as $index => $item) {
                self::spans($item, "{$path}.items.{$index}");
            }

            return;
        }

        self::spans($block['children'] ?? null, "{$path}.children");
    }

    private static function spans(mixed $spans, string $path): void
    {
        if (! is_array($spans) || ! array_is_list($spans)) {
            throw InvalidRichText::at($path, 'must be a list.');
        }

        foreach ($spans as $index => $span) {
            self::object($span, "{$path}.{$index}", ['text', 'marks', 'link']);

            if (! is_string($span['text'] ?? null)) {
                throw InvalidRichText::at("{$path}.{$index}.text", 'must be a string.');
            }

            $marks = $span['marks'] ?? [];

            if (! is_array($marks) || ! array_is_list($marks) || array_diff($marks, self::MARKS) !== []) {
                throw InvalidRichText::at("{$path}.{$index}.marks", 'may only be bold, italic and underline.');
            }

            if (array_key_exists('link', $span)) {
                self::object($span['link'], "{$path}.{$index}.link", ['href']);

                $href = $span['link']['href'] ?? null;

                if (! is_string($href) || ! preg_match('#^(https?://[^\s]+|mailto:[^\s]+)$#i', $href)) {
                    throw InvalidRichText::at("{$path}.{$index}.link.href", 'must be an absolute http, https or mailto address.');
                }
            }
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
