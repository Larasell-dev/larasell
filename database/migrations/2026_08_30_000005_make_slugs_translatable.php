<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['larasell_products', 'larasell_categories'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropUnique(['slug']);
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->json('slug_translations')->nullable();
            });

            $locale = (string) config('app.fallback_locale', 'en');

            DB::table($table)->select(['id', 'slug'])->orderBy('id')->each(
                fn (object $model) => DB::table($table)->where('id', $model->id)->update([
                    'slug_translations' => json_encode([$locale => $model->slug], JSON_THROW_ON_ERROR),
                ]),
            );

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('slug');
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->renameColumn('slug_translations', 'slug');
            });
        }
    }

    public function down(): void
    {
        foreach (['larasell_products', 'larasell_categories'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('slug_string')->nullable();
            });

            $locale = (string) config('app.fallback_locale', 'en');

            DB::table($table)->select(['id', 'slug'])->orderBy('id')->each(function (object $model) use ($locale, $table): void {
                $translations = json_decode($model->slug, true, flags: JSON_THROW_ON_ERROR);

                DB::table($table)->where('id', $model->id)->update([
                    'slug_string' => $translations[$locale] ?? array_values($translations)[0],
                ]);
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('slug');
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->renameColumn('slug_string', 'slug');
                $blueprint->unique('slug');
            });
        }
    }
};
