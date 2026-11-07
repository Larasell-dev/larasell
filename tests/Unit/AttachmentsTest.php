<?php

use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Attachments\Exceptions\DuplicateAttachment;

final readonly class AttachmentsTestNote
{
    public function __construct(public string $text) {}
}

final readonly class AttachmentsTestTag
{
    public function __construct(public string $tag) {}
}

it('gives each object back by its class', function () {
    $attachments = new Attachments(new AttachmentsTestNote('note'), new AttachmentsTestTag('tag'));

    expect($attachments->get(AttachmentsTestNote::class)?->text)->toBe('note')
        ->and($attachments->get(AttachmentsTestTag::class)?->tag)->toBe('tag')
        ->and((new Attachments)->get(AttachmentsTestNote::class))->toBeNull();
});

it('refuses two objects of the same class instead of keeping the last', function () {
    expect(fn () => new Attachments(new AttachmentsTestNote('first'), new AttachmentsTestNote('second')))
        ->toThrow(DuplicateAttachment::class, AttachmentsTestNote::class);
});
