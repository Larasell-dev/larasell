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
            $table->json('description_translations')->nullable();
        });

        $locale = (string) config('app.fallback_locale', 'en');

        DB::table('larasell_products')->select(['id', 'description'])->whereNotNull('description')->orderBy('id')->each(
            fn (object $product) => DB::table('larasell_products')->where('id', $product->id)->update([
                'description_translations' => json_encode([$locale => $product->description], JSON_THROW_ON_ERROR),
            ])
        );

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->renameColumn('description_translations', 'description');
        });
    }

    public function down(): void
    {
        Schema::table('larasell_products', function (Blueprint $table) {
            $table->text('description_text')->nullable();
        });

        $locale = (string) config('app.fallback_locale', 'en');

        DB::table('larasell_products')->select(['id', 'description'])->whereNotNull('description')->orderBy('id')->each(function (object $product) use ($locale): void {
            $translations = json_decode($product->description, true, flags: JSON_THROW_ON_ERROR);

            DB::table('larasell_products')->where('id', $product->id)->update([
                'description_text' => $translations[$locale] ?? array_values($translations)[0],
            ]);
        });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->renameColumn('description_text', 'description');
        });
    }
};
