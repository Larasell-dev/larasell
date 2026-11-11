<?php

namespace Larasell\Larasell\Audit\Enums;

/**
 * Groups records by why they're kept, e.g. to find every security record.
 *
 * @public
 */
enum AuditCategory: string
{
    case Security = 'security';
    case BusinessCritical = 'business_critical';
    case AuditAccess = 'audit_access';
}
