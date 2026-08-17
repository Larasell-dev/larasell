<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $adminVite = (clone app(Illuminate\Foundation\Vite::class))
            ->useHotFile(public_path('vendor/larasell/admin/hot'));
    @endphp

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Larasell Admin</title>

        {{ $adminVite->reactRefresh() }}
        {{ $adminVite('resources/js/admin/app.tsx', 'vendor/larasell/admin') }}
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
