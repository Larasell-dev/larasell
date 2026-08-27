<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_products', function (Blueprint $table) {
            $table->json('name_translations')->nullable();
        });

        $locale = (string) config('app.fallback_locale', 'en');

        DB::table('larasell_products')->select(['id', 'name'])->orderBy('id')->each(
            fn (object $product) => DB::table('larasell_products')->where('id', $product->id)->update([
                'name_translations' => json_encode([$locale => $product->name], JSON_THROW_ON_ERROR),
            ])
        );

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->renameColumn('name_translations', 'name');
        });
    }

    public function down(): void
    {
        Schema::table('larasell_products', function (Blueprint $table) {
            $table->string('name_string')->nullable();
        });

        $locale = (string) config('app.fallback_locale', 'en');

        DB::table('larasell_products')->select(['id', 'name'])->orderBy('id')->each(function (object $product) use ($locale): void {
            $translations = json_decode($product->name, true, flags: JSON_THROW_ON_ERROR);

            DB::table('larasell_products')->where('id', $product->id)->update([
                'name_string' => $translations[$locale] ?? array_values($translations)[0],
            ]);
        });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->renameColumn('name_string', 'name');
        });
    }
};
