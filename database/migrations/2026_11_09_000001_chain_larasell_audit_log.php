<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_audit_log', function (Blueprint $table) {
            $table->unsignedBigInteger('seq')->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64)->nullable();
        });

        Schema::create('larasell_audit_log_head', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('seq');
            $table->char('hash', 64)->nullable();
        });

        // Records stored before the chain are left unchained; verification reports them.
        Schema::table('larasell_audit_log', function (Blueprint $table) {
            $table->unsignedBigInteger('seq')->nullable(false)->change();
            $table->char('hash', 64)->nullable(false)->change();
            $table->unique('seq');
        });
    }
};
