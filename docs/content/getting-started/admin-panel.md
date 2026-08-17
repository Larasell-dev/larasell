---
title: Admin panel
description: Register the optional Larasell administration panel.
---

# Admin panel

Larasell ships an optional administration panel under the
`Larasell\Larasell\Admin` namespace. The admin panel is not registered
through package discovery. Register it yourself when you want to use it:

```php
// bootstrap/providers.php

return [
    App\Providers\AppServiceProvider::class,

    Larasell\Larasell\Admin\LarasellAdminServiceProvider::class,
];
```

After registering the provider, run the migrations:

```bash
php artisan migrate
```

Publish the compiled admin assets:

```bash
php artisan vendor:publish --tag=larasell-admin-assets
```

The provider creates a separate `larasell-admin` auth guard and stores
admin users in the `larasell_admin_users` table.

The admin panel is a React Inertia app. Install and configure Inertia in
the host Laravel application before registering the admin provider:

```bash
composer require inertiajs/inertia-laravel
npm install @inertiajs/react react react-dom @stylexjs/stylex
npm install --save-dev @stylexjs/unplugin
```

## Create an admin user

Create the first admin user with the package command:

```bash
php artisan admin:create-user
```

You can also pass the values non-interactively:

```bash
php artisan admin:create-user \
    --name="Larasell Admin" \
    --email="admin@example.com" \
    --password="password"
```

Only the compiled JavaScript, CSS, and Vite manifest are published. The
configuration, routes, migrations, views, and source files continue to
load directly from the package.

The login page is available at `/admin/login` by default.
