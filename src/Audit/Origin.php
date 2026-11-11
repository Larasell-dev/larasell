<?php

namespace Larasell\Larasell\Audit;

use Larasell\Larasell\Audit\Actors\Actor;
use Larasell\Larasell\Audit\Enums\AuditSource;

/**
 * Who made a change and how it came in. The app that handles the request, job or command knows
 * both and passes them along; the core never guesses them.
 *
 * @public
 */
final readonly class Origin
{
    public function __construct(public Actor $actor, public AuditSource $source) {}

    public static function web(Actor $actor): self
    {
        return new self($actor, AuditSource::Web);
    }

    public static function api(Actor $actor): self
    {
        return new self($actor, AuditSource::Api);
    }

    public static function cli(Actor $actor): self
    {
        return new self($actor, AuditSource::Cli);
    }

    public static function job(Actor $actor): self
    {
        return new self($actor, AuditSource::Job);
    }

    /**
     * For a job that runs for whoever dispatched it: the same actor, from a job.
     */
    public function inJob(): self
    {
        return new self($this->actor, AuditSource::Job);
    }
}
