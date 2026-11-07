<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The highest number each sequence has issued; the next one is claimed from there.
        $counters = DB::table('larasell_order_number_sequences')
            ->groupBy('name')
            ->pluck(DB::raw('max(number) as number'), 'name');

        Schema::drop('larasell_order_number_sequences');

        Schema::create('larasell_order_number_sequences', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('number');
        });

        DB::table('larasell_order_number_sequences')->insert(
            $counters->map(fn ($number, $name): array => ['name' => (string) $name, 'number' => (int) $number])->values()->all(),
        );
    }

    public function down(): void
    {
        $counters = DB::table('larasell_order_number_sequences')->pluck('number', 'name');

        Schema::drop('larasell_order_number_sequences');

        Schema::create('larasell_order_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('number');
            $table->timestamp('created_at');
            $table->unique(['name', 'number']);
        });

        DB::table('larasell_order_number_sequences')->insert(
            $counters->map(fn ($number, $name): array => ['name' => (string) $name, 'number' => (int) $number, 'created_at' => now()])->values()->all(),
        );
    }
};
