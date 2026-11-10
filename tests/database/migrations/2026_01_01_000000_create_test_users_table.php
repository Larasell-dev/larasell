<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An app's own users, for tests that link them to customers. Created with the other migrations,
 * since creating a table inside a test commits MySQL's test transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->timestamps();
        });
    }
};
