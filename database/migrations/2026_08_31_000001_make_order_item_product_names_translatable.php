<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->json('product_name_translations')->nullable();
        });

        $locale = (string) config('app.fallback_locale', 'en');

        DB::table('larasell_order_items')->select(['id', 'product_name'])->orderBy('id')->each(
            fn (object $item) => DB::table('larasell_order_items')->where('id', $item->id)->update([
                'product_name_translations' => json_encode([$locale => $item->product_name], JSON_THROW_ON_ERROR),
            ])
        );

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->renameColumn('product_name_translations', 'product_name');
        });
    }

    public function down(): void
    {
        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->string('product_name_string')->nullable();
        });

        $locale = (string) config('app.fallback_locale', 'en');

        DB::table('larasell_order_items')->select(['id', 'product_name'])->orderBy('id')->each(function (object $item) use ($locale): void {
            $translations = json_decode($item->product_name, true, flags: JSON_THROW_ON_ERROR);

            DB::table('larasell_order_items')->where('id', $item->id)->update([
                'product_name_string' => $translations[$locale] ?? array_values($translations)[0],
            ]);
        });

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->renameColumn('product_name_string', 'product_name');
        });
    }
};
