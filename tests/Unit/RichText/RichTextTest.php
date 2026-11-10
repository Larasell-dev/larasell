<?php

declare(strict_types=1);

use Larasell\Larasell\Exceptions\InvalidRichText;
use Larasell\Larasell\RichText\RichText;

/** A Portable Text block with spans given as [text, marks]. */
function ptBlock(string $key, array $spans, array $extra = [], array $markDefs = []): array
{
    return [
        '_type' => 'block',
        '_key' => $key,
        'style' => 'normal',
        ...$extra,
        'markDefs' => $markDefs,
        'children' => array_map(fn (array $span, int $i): array => [
            '_type' => 'span', '_key' => "{$key}s{$i}", 'text' => $span[0], 'marks' => $span[1] ?? [],
        ], $spans, array_keys($spans)),
    ];
}

it('renders every style, list, decorator and link as HTML', function () {
    $text = RichText::fromArray([
        ptBlock('a', [['Material']], ['style' => 'h3']),
        ptBlock('b', [
            ['Soft '],
            ['organic', ['strong', 'em', 'underline']],
            [' cotton, see the '],
            ['care guide', ['strong', 'l']],
        ], markDefs: [['_type' => 'link', '_key' => 'l', 'href' => 'https://example.com/care']]),
        ptBlock('c', [['Wash cold']], ['listItem' => 'bullet', 'level' => 1]),
        ptBlock('d', [['Hang dry']], ['listItem' => 'bullet', 'level' => 1]),
        ptBlock('e', [['Unpack']], ['listItem' => 'number', 'level' => 1]),
    ]);

    expect($text->toHtml())->toBe(
        '<h3>Material</h3>'
        .'<p>Soft <strong><em><u>organic</u></em></strong> cotton, see the <a href="https://example.com/care"><strong>care guide</strong></a></p>'
        .'<ul><li>Wash cold</li><li>Hang dry</li></ul>'
        .'<ol><li>Unpack</li></ol>',
    );
});

it('escapes text and link addresses, so the HTML is safe to show as it is', function () {
    $text = RichText::fromArray([
        ptBlock('a', [['<script>alert(1)</script> & more'], ['quote', ['l']]], markDefs: [
            ['_type' => 'link', '_key' => 'l', 'href' => 'https://example.com/?a=1&b="x"'],
        ]),
    ]);

    expect($text->toHtml())->toBe(
        '<p>&lt;script&gt;alert(1)&lt;/script&gt; &amp; more'
        .'<a href="https://example.com/?a=1&amp;b=&quot;x&quot;">quote</a></p>',
    );
});

it('reads its text without formatting, one line per block', function () {
    $text = RichText::fromArray([
        ptBlock('a', [['Care']], ['style' => 'h2']),
        ptBlock('b', [['Wash '], ['cold', ['strong']]]),
        ptBlock('c', [['No dryer']], ['listItem' => 'bullet']),
    ]);

    expect($text->toPlainText())->toBe("Care\nWash cold\nNo dryer");
});

it('stores itself as the JSON it is read from', function () {
    $document = [ptBlock('a', [['Boots', ['em']]])];

    $text = RichText::fromJson(json_encode($document, JSON_THROW_ON_ERROR));

    expect($text->toArray())->toBe($document)
        ->and(json_decode($text->toJson(), true))->toBe($document);
});

it('reads a description saved as plain text as one paragraph per line', function () {
    expect(RichText::fromStored("Warm boots.\n\nMade in Portugal.")->toArray())->toBe([
        ptBlock('b0', [['Warm boots.']]),
        ptBlock('b1', [['Made in Portugal.']]),
    ]);
});

it('is empty without any text', function (array $blocks, bool $empty) {
    expect(RichText::fromArray($blocks)->isEmpty())->toBe($empty);
})->with([
    'no blocks' => [[], true],
    'only empty blocks' => [[ptBlock('a', []), ptBlock('b', [['']])], true],
    'a space' => [[ptBlock('a', [[' ']])], false],
]);

it('rejects documents it does not support', function (array $document) {
    RichText::fromArray($document);
})->throws(InvalidRichText::class)->with([
    'not a list' => [['_type' => 'block']],
    'unknown type' => [[['_type' => 'image', '_key' => 'a']]],
    'no key' => [[array_diff_key(ptBlock('a', []), ['_key' => 0])]],
    'h1' => [[ptBlock('a', [], ['style' => 'h1'])]],
    'blockquote' => [[ptBlock('a', [], ['style' => 'blockquote'])]],
    'unknown list' => [[ptBlock('a', [], ['listItem' => 'check'])]],
    'nested list' => [[ptBlock('a', [], ['listItem' => 'bullet', 'level' => 2])]],
    'heading list item' => [[ptBlock('a', [], ['style' => 'h2', 'listItem' => 'bullet'])]],
    'unknown decorator' => [[ptBlock('a', [['a', ['strike-through']]])]],
    'mark without a definition' => [[ptBlock('a', [['a', ['l']]])]],
    'text that is not a string' => [[['_type' => 'block', '_key' => 'a', 'style' => 'normal', 'children' => [['_type' => 'span', '_key' => 's', 'text' => 1]]]]],
    'unknown key' => [[[...ptBlock('a', []), 'css' => 'color: red']]],
    'javascript link' => [[ptBlock('a', [['a', ['l']]], markDefs: [['_type' => 'link', '_key' => 'l', 'href' => 'javascript:alert(1)']])]],
    'relative link' => [[ptBlock('a', [['a', ['l']]], markDefs: [['_type' => 'link', '_key' => 'l', 'href' => '/care']])]],
]);

it('accepts http, https and mailto links', function (string $href) {
    $text = RichText::fromArray([ptBlock('a', [['a', ['l']]], markDefs: [['_type' => 'link', '_key' => 'l', 'href' => $href]])]);

    expect($text->toArray()[0]['markDefs'][0]['href'])->toBe($href);
})->with(['http://example.com', 'HTTPS://example.com/a?b=c', 'mailto:care@example.com']);
