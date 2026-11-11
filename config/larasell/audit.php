<?php

return [

    /*
     * How many days to keep audit records, counted from when the action happened. Null keeps them
     * forever, the default, since how long to keep them is up to the shop's obligations.
     *
     * Records older than the period are deleted when `php artisan larasell:prune-audit-log` runs.
     * Nothing deletes them otherwise, so schedule the command, e.g. daily, in routes/console.php:
     *
     *     Schedule::command('larasell:prune-audit-log')->daily();
     *
     * The records are chained, so only the oldest are deleted: the command stops at the first one
     * still in the period, and an older record recorded after it is kept until that one goes.
     *
     * Whoever can deploy this file can change this setting. Each run of the command records the
     * period it applied as an audit_log.pruned record, so a change shows in the audit log from the
     * first run it applies to.
     */
    'retention' => [
        'days' => env('LARASELL_AUDIT_RETENTION_DAYS'),
    ],

    /*
     * While true, larasell:prune-audit-log deletes no records, e.g. while a legal hold requires
     * keeping them. It still records each run, so the log shows the hold was in place.
     */
    'legal_hold' => env('LARASELL_AUDIT_LEGAL_HOLD', false),

];
