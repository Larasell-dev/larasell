<?php

use Larasell\Larasell\Images\Placeholder;

it('normalizes placeholder color to lowercase hex', function () {
    $placeholder = Placeholder::fromArray([
        'type' => 'color',
        'value' => '#AABBCC',
        'color' => '#AABBCC',
    ]);

    expect($placeholder->toArray())->toBe([
        'type' => 'color',
        'value' => '#AABBCC',
        'color' => '#aabbcc',
    ]);
});

it('rejects an incomplete placeholder payload', function () {
    Placeholder::fromArray(['type' => 'color']);
})->throws(InvalidArgumentException::class);
