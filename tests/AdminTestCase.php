<?php

namespace Larasell\Larasell\Tests;

use Larasell\Larasell\Admin\LarasellAdminServiceProvider;

abstract class AdminTestCase extends TestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            LarasellAdminServiceProvider::class,
        ];
    }
}
