<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_categories', function (Blueprint $table): void {
            $table->unsignedInteger('position')->default(0);
            $table->index(['parent_id', 'position'], 'category_position_idx');
        });

        // Keeps the order categories had before, by id, among each set of siblings.
        $positions = [];

        DB::table('larasell_categories')->select(['id', 'parent_id'])->orderBy('id')->each(
            function (object $row) use (&$positions): void {
                $parent = $row->parent_id ?? 'root';
                $positions[$parent] ??= 0;

                DB::table('larasell_categories')->where('id', $row->id)->update([
                    'position' => $positions[$parent]++,
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::table('larasell_categories', function (Blueprint $table): void {
            $table->dropIndex('category_position_idx');
            $table->dropColumn('position');
        });
    }
};
