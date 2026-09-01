<?php

namespace Larasell\Larasell\Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;

abstract class ConcurrencyTestCase extends TestCase
{
    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            // Forked processes commit independently, so the next transactional test must remigrate.
            RefreshDatabaseState::$migrated = false;
        }
    }
}
