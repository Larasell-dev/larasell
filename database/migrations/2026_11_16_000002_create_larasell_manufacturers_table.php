<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Manufacturers, the legal producers the GPSR has product pages name. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('address');
            $table->string('email')->nullable();
            $table->string('url')->nullable();
            $table->json('responsible_person')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_manufacturers');
    }
};
