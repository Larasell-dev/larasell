<?php

namespace Larasell\Larasell\Audit\Actors;

/**
 * Who did an audited action: e.g. an admin, an API token, a SystemActor for jobs and commands, or an UnknownActor.
 * Who it is, not how they signed in; the record's source says that.
 *
 * @public Implement it for anything else that acts, e.g. an integration.
 */
interface Actor
{
    /**
     * A stable name such as admin or api_token, rather than a class name.
     */
    public function auditActorType(): string;

    /**
     * Null only for an UnknownActor.
     */
    public function auditActorId(): ?string;
}
