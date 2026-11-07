<?php

use Larasell\Larasell\Exceptions\DuplicateExtension;
use Larasell\Larasell\Extensions;

final readonly class ExtensionsTestNote
{
    public function __construct(public string $text) {}
}

final readonly class ExtensionsTestTag
{
    public function __construct(public string $tag) {}
}

it('gives each object back by its class', function () {
    $extensions = new Extensions(new ExtensionsTestNote('note'), new ExtensionsTestTag('tag'));

    expect($extensions->get(ExtensionsTestNote::class)?->text)->toBe('note')
        ->and($extensions->get(ExtensionsTestTag::class)?->tag)->toBe('tag')
        ->and((new Extensions)->get(ExtensionsTestNote::class))->toBeNull();
});

it('refuses two objects of the same class instead of keeping the last', function () {
    expect(fn () => new Extensions(new ExtensionsTestNote('first'), new ExtensionsTestNote('second')))
        ->toThrow(DuplicateExtension::class, ExtensionsTestNote::class);
});
