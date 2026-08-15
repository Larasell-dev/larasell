---
title: Building A Navigation
description: Share a category navigation with your Inertia pages.
---

You'll learn how to create a category navigation, so that customers
are able to browse the store from every page.

## Sharing the navigation

First up, share the navigation from your Inertia middleware.

```php
// app/Http/Middleware/HandleInertiaRequests.php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'navigation' => fn () => $this->navigation(),
        ];
    }
}
```

Using a closure keeps the navigation lazy, so it is only built when
Inertia needs the prop.

## Getting root categories

Next up, fetch the root-level category collection and load the
categories you want to show in the menu.

```php
<?php

use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Routing\CategoryUrl;

private function navigation(): array
{
    return Category::query()
        ->root()
        ->get()
        ->map(fn (Category $category) => $this->navigationItem($category))
        ->values()
        ->all();
}

private function navigationItem(Category $category): array
{
    return [
        'name' => $category->name,
        'url' => CategoryUrl::make($category, prefix: 'c'),
        'children' => $category->children
            ->map(fn (Category $category) => $this->navigationItem($category))
            ->values()
            ->all(),
    ];
}
```

The `root()` scope returns the visible categories with no parent. Each
navigation item then recursively renders its visible child categories.

## Rendering a menu

Finally, read the shared `navigation` prop from your layout and render
the links.

```jsx
// resources/js/Layouts/AppLayout.jsx
import { Link, usePage } from '@inertiajs/react';

function NavigationItems({ items }) {
    return (
        <ul>
            {items.map((item) => (
                <li key={item.url}>
                    <Link href={item.url}>{item.name}</Link>

                    {item.children.length > 0 && (
                        <NavigationItems items={item.children} />
                    )}
                </li>
            ))}
        </ul>
    );
}

export default function AppLayout({ children }) {
    const { navigation } = usePage().props;

    return (
        <>
            <nav>
                <NavigationItems items={navigation} />
            </nav>

            {children}
        </>
    );
}
```

## Performance

If the navigation does not change often, wrap the query in Laravel's
cache and clear it whenever categories are updated.
