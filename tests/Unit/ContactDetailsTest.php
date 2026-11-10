<?php

use Larasell\Larasell\EmailAddress;
use Larasell\Larasell\Exceptions\InvalidEmailAddress;
use Larasell\Larasell\Exceptions\InvalidPhoneNumber;
use Larasell\Larasell\Exceptions\InvalidUrl;
use Larasell\Larasell\PhoneNumber;
use Larasell\Larasell\Url;

it('trims an email address and lowercases only its domain', function () {
    expect(EmailAddress::of('  Erika.Muster@Example.COM ')->value())->toBe('Erika.Muster@example.com');
});

it('rejects what is no email address', function (string $input) {
    expect(fn () => EmailAddress::of($input))->toThrow(InvalidEmailAddress::class);
})->with(['empty' => [' '], 'no at' => ['erika.example.com'], 'no domain' => ['erika@'], 'space' => ['er ika@example.com']]);

it('keeps a phone number as typed, only trimmed', function (string $input, string $expected) {
    expect(PhoneNumber::of($input)->value())->toBe($expected);
})->with([
    'international' => [' +49 (0)30 123-456 ', '+49 (0)30 123-456'],
    'slashes and dots' => ['030/123.456', '030/123.456'],
]);

it('rejects what is no phone number', function (string $input) {
    expect(fn () => PhoneNumber::of($input))->toThrow(InvalidPhoneNumber::class);
})->with([
    'empty' => ['  '],
    'letters' => ['call me'],
    'too few digits' => ['+4'],
    'too long' => [str_repeat('1', 33)],
]);

it('adds https to a URL without a scheme and keeps the rest', function (string $input, string $expected) {
    expect(Url::of($input)->value())->toBe($expected);
})->with([
    'bare host' => ['muster.de', 'https://muster.de'],
    'with path' => [' muster.de/shop?a=1 ', 'https://muster.de/shop?a=1'],
    'http kept' => ['http://muster.de', 'http://muster.de'],
    'uppercase scheme' => ['HTTPS://muster.de', 'HTTPS://muster.de'],
]);

it('rejects what is no web address', function (string $input) {
    expect(fn () => Url::of($input))->toThrow(InvalidUrl::class);
})->with([
    'empty' => [' '],
    'other scheme' => ['ftp://muster.de'],
    'javascript' => ['javascript:alert(1)'],
    'no host' => ['https://'],
    'space in host' => ['muster de'],
]);
