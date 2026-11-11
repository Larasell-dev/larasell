<?php

namespace Larasell\Larasell\Audit\Enums;

/**
 * How an audited change came in, apart from who made it. Set by the app through an Origin.
 *
 * @public
 */
enum AuditSource: string
{
    case Web = 'web';
    case Api = 'api';
    case Cli = 'cli';
    case Job = 'job';
}
