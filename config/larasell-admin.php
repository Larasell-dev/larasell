<?php

use Larasell\Larasell\Admin\Models\AdminUser;

return [
    'path' => env('LARASELL_ADMIN_PATH', 'admin'),

    'home' => 'larasell.admin.home',

    'routes' => base_path('routes/larasell-admin.php'),

    'middleware' => [
        'web',
    ],

    'guard' => 'larasell-admin',

    'passwords' => 'larasell-admin-users',

    'models' => [
        'admin_user' => AdminUser::class,
    ],
];
