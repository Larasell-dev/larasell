<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Larasell\Larasell\Settings\LocaleSettings;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['larasell_product_attributes', 'larasell_product_attribute_values'];

    public function up(): void
    {
        $locale = app(LocaleSettings::class)->default()->value;

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->json('name_translations')->nullable();
            });

            DB::table($table)->select(['id', 'name'])->orderBy('id')->each(
                fn (object $row) => DB::table($table)->where('id', $row->id)->update([
                    'name_translations' => json_encode([$locale => $row->name], JSON_THROW_ON_ERROR),
                ]),
            );

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('name');
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->renameColumn('name_translations', 'name');
            });
        }
    }

    public function down(): void
    {
        $locale = app(LocaleSettings::class)->default()->value;

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('name_string')->nullable();
            });

            DB::table($table)->select(['id', 'name'])->orderBy('id')->each(function (object $row) use ($table, $locale): void {
                $translations = json_decode($row->name, true, flags: JSON_THROW_ON_ERROR);

                DB::table($table)->where('id', $row->id)->update([
                    'name_string' => $translations[$locale] ?? array_values($translations)[0],
                ]);
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('name');
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->renameColumn('name_string', 'name');
            });
        }
    }
};
