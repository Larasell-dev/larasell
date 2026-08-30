<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('larasell_product_attributes')
            ->where('type', 'boolean')
            ->pluck('id')
            ->each(function ($optionId) use ($now): void {
                DB::table('larasell_product_attribute_values')->updateOrInsert(
                    ['product_attribute_id' => $optionId, 'slug' => '__boolean_true'],
                    ['name' => 'Yes', 'value' => json_encode(true), 'position' => 0, 'created_at' => $now, 'updated_at' => $now],
                );
                DB::table('larasell_product_attribute_values')->updateOrInsert(
                    ['product_attribute_id' => $optionId, 'slug' => '__boolean_false'],
                    ['name' => 'No', 'value' => json_encode(false), 'position' => 1, 'created_at' => $now, 'updated_at' => $now],
                );
            });
    }

    public function down(): void
    {
        DB::table('larasell_product_attribute_values')
            ->whereIn('slug', ['__boolean_true', '__boolean_false'])
            ->delete();
    }
};
