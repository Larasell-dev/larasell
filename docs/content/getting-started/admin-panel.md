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

## Publish the admin files

Publish the admin configuration, routes, migration, views, and Inertia
pages with the `larasell-admin` tag:

```bash
php artisan vendor:publish --tag=larasell-admin
```

The published configuration lives at `config/larasell-admin.php`. Use it
to change the admin path, route file, middleware, guard name, or admin
user model:

```php
return [
    'path' => 'admin/commerce',

    'routes' => base_path('routes/larasell-admin.php'),

    'middleware' => [
        'web',
    ],

    'guard' => 'larasell-admin',
];
```

## Inertia pages

The package publishes its admin pages to:

```txt
resources/js/vendor/larasell/admin/Pages
```

The package also publishes the StyleX CSS entrypoint to:

```txt
resources/css/vendor/larasell/admin.css
```

Configure StyleX before the React plugin in your Vite config:

```ts
import stylex from '@stylexjs/unplugin'

export default defineConfig({
  plugins: [
    stylex.vite({
      useCSSLayers: true,
    }),
    react(),
  ],
})
```

Make sure your application Inertia page resolver can find the published
admin pages. For example, in a React Inertia app:

```ts
const pages = import.meta.glob([
  './Pages/**/*.tsx',
  './vendor/larasell/admin/Pages/**/*.tsx',
])
```

Import the published CSS entrypoint from your app entry:

```ts
import '../css/vendor/larasell/admin.css'
```

The login page is available at `/admin/login` by default.
