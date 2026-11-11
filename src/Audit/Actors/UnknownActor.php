<?php

namespace Larasell\Larasell\Audit\Actors;

use Larasell\Larasell\Audit\Exceptions\AuditFailed;

/**
 * For the rare change whose actor can't be known, with the reason why, e.g. data imported from
 * a previous system. Never a stand-in for an actor that could be passed.
 *
 * @public
 */
final readonly class UnknownActor implements Actor
{
    public function __construct(public string $reason)
    {
        if (trim($reason) === '') {
            throw AuditFailed::unnamedActor(self::class, 'reason');
        }
    }

    public function auditActorType(): string
    {
        return 'unknown';
    }

    public function auditActorId(): ?string
    {
        return null;
    }
}
