<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Translatable;

it('casts a category name from translations', function () {
    $category = Category::query()->create([
        'slug' => ['en' => 'lighting'],
        'name' => ['en' => 'Lighting', 'de' => 'Beleuchtung'],
    ]);

    App::setLocale('de');
    $name = $category->fresh()->name;
    $storedName = DB::table('larasell_categories')->where('id', $category->id)->value('name');

    expect($name)->toBeInstanceOf(Translatable::class)
        ->and($name->get())->toBe('Beleuchtung')
        ->and(json_decode($storedName, true))->toEqualCanonicalizing([
            'en' => 'Lighting',
            'de' => 'Beleuchtung',
        ]);
});

it('accepts a string category name for the current locale', function () {
    App::setLocale('de');

    $category = Category::query()->create([
        'slug' => ['en' => 'lighting'],
        'name' => 'Beleuchtung',
    ]);

    expect($category->fresh()->name->all())->toBe(['de' => 'Beleuchtung']);
});
