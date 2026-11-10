<?php

declare(strict_types=1);

use Larasell\Larasell\Exceptions\InvalidRichText;
use Larasell\Larasell\RichText\RichText;

it('renders every block and mark as HTML', function () {
    $text = RichText::fromArray(['version' => 1, 'blocks' => [
        ['type' => 'heading', 'level' => 3, 'children' => [['text' => 'Material']]],
        ['type' => 'paragraph', 'children' => [
            ['text' => 'Soft '],
            ['text' => 'organic', 'marks' => ['bold', 'italic', 'underline']],
            ['text' => ' cotton, see the '],
            ['text' => 'care guide', 'marks' => ['bold'], 'link' => ['href' => 'https://example.com/care']],
        ]],
        ['type' => 'list', 'style' => 'bullet', 'items' => [[['text' => 'Wash cold']], [['text' => 'Hang dry']]]],
        ['type' => 'list', 'style' => 'numbered', 'items' => [[['text' => 'Unpack']]]],
    ]]);

    $html = $text->toHtml();

    expect($html)->toBe(
        '<h3>Material</h3>'
        .'<p>Soft <strong><em><u>organic</u></em></strong> cotton, see the <a href="https://example.com/care"><strong>care guide</strong></a></p>'
        .'<ul><li>Wash cold</li><li>Hang dry</li></ul>'
        .'<ol><li>Unpack</li></ol>',
    );
});

it('escapes text and link addresses, so the HTML is safe to show as it is', function () {
    $text = RichText::fromArray(['version' => 1, 'blocks' => [
        ['type' => 'paragraph', 'children' => [
            ['text' => '<script>alert(1)</script> & more'],
            ['text' => 'quote', 'link' => ['href' => 'https://example.com/?a=1&b="x"']],
        ]],
    ]]);

    $html = $text->toHtml();

    expect($html)->toBe(
        '<p>&lt;script&gt;alert(1)&lt;/script&gt; &amp; more'
        .'<a href="https://example.com/?a=1&amp;b=&quot;x&quot;">quote</a></p>',
    );
});

it('reads its text without formatting, one line per block and list item', function () {
    $text = RichText::fromArray(['version' => 1, 'blocks' => [
        ['type' => 'heading', 'level' => 2, 'children' => [['text' => 'Care']]],
        ['type' => 'paragraph', 'children' => [['text' => 'Wash '], ['text' => 'cold', 'marks' => ['bold']]]],
        ['type' => 'list', 'style' => 'bullet', 'items' => [[['text' => 'No dryer']], [['text' => 'No bleach']]]],
    ]]);

    expect($text->toPlainText())->toBe("Care\nWash cold\nNo dryer\nNo bleach");
});

it('stores itself as the JSON it is read from', function () {
    $document = ['version' => 1, 'blocks' => [
        ['type' => 'paragraph', 'children' => [['text' => 'Boots', 'marks' => ['italic']]]],
    ]];

    $text = RichText::fromJson(json_encode($document, JSON_THROW_ON_ERROR));

    expect($text->toArray())->toBe($document)
        ->and(json_decode($text->toJson(), true))->toBe($document);
});

it('reads a description saved as plain text as one paragraph per line', function () {
    $text = RichText::fromStored("Warm boots.\n\nMade in Portugal.");

    expect($text->toArray())->toBe(['version' => 1, 'blocks' => [
        ['type' => 'paragraph', 'children' => [['text' => 'Warm boots.']]],
        ['type' => 'paragraph', 'children' => [['text' => 'Made in Portugal.']]],
    ]]);
});

it('is empty without any text', function (array $blocks, bool $empty) {
    $text = RichText::fromArray(['version' => 1, 'blocks' => $blocks]);

    expect($text->isEmpty())->toBe($empty);
})->with([
    'no blocks' => [[], true],
    'only empty paragraphs' => [[['type' => 'paragraph', 'children' => []], ['type' => 'paragraph', 'children' => [['text' => '']]]], true],
    'a space' => [[['type' => 'paragraph', 'children' => [['text' => ' ']]]], false],
]);

it('rejects documents it does not support', function (array $document) {
    expect(fn () => RichText::fromArray($document))->toThrow(InvalidRichText::class);
})->with([
    'unknown version' => [['version' => 2, 'blocks' => []]],
    'no blocks' => [['version' => 1]],
    'unknown block' => [['version' => 1, 'blocks' => [['type' => 'table', 'children' => []]]]],
    'heading level 1' => [['version' => 1, 'blocks' => [['type' => 'heading', 'level' => 1, 'children' => []]]]],
    'heading level 5' => [['version' => 1, 'blocks' => [['type' => 'heading', 'level' => 5, 'children' => []]]]],
    'unknown list style' => [['version' => 1, 'blocks' => [['type' => 'list', 'style' => 'check', 'items' => []]]]],
    'unknown mark' => [['version' => 1, 'blocks' => [['type' => 'paragraph', 'children' => [['text' => 'a', 'marks' => ['strikethrough']]]]]]],
    'text that is not a string' => [['version' => 1, 'blocks' => [['type' => 'paragraph', 'children' => [['text' => 1]]]]]],
    'unknown key' => [['version' => 1, 'blocks' => [['type' => 'paragraph', 'children' => [], 'style' => 'color: red']]]],
    'javascript link' => [['version' => 1, 'blocks' => [['type' => 'paragraph', 'children' => [['text' => 'a', 'link' => ['href' => 'javascript:alert(1)']]]]]]],
    'relative link' => [['version' => 1, 'blocks' => [['type' => 'paragraph', 'children' => [['text' => 'a', 'link' => ['href' => '/care']]]]]]],
]);

it('accepts http, https and mailto links', function (string $href) {
    $text = RichText::fromArray(['version' => 1, 'blocks' => [
        ['type' => 'paragraph', 'children' => [['text' => 'a', 'link' => ['href' => $href]]]],
    ]]);

    expect($text->toArray()['blocks'][0]['children'][0]['link']['href'])->toBe($href);
})->with(['http://example.com', 'HTTPS://example.com/a?b=c', 'mailto:care@example.com']);
