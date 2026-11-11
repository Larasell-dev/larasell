<?php

namespace Larasell\Larasell\Seo\Listeners;

use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Seo\AuditRecords\SeoUrlReleased as Released;
use Larasell\Larasell\Seo\AuditRecords\SeoUrlRestored as Restored;
use Larasell\Larasell\Seo\Events\SeoUrlReleased;
use Larasell\Larasell\Seo\Events\SeoUrlRestored;

/**
 * Audits old URLs being released and restored, with the origin of the change.
 *
 * @internal Registered by SeoServiceProvider.
 */
class AuditSeoChanges
{
    public function __construct(private readonly Auditor $auditor) {}

    /** Dispatched once committed, so the record is stored right after the change and can't roll it back. */
    public function released(SeoUrlReleased $event): void
    {
        $this->auditor->record(new Released($event), $event->origin);
    }

    public function restored(SeoUrlRestored $event): void
    {
        $this->auditor->record(new Restored($event), $event->origin);
    }
}
