<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_audit_log', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('event');
            $table->unsignedSmallInteger('event_version');
            $table->string('category');
            $table->string('actor_type');
            $table->string('actor_id')->nullable();
            $table->string('unknown_actor_reason')->nullable();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->json('properties');
            $table->string('source');
            $table->string('correlation_id')->nullable();
            // dateTime rather than timestamp, so MySQL doesn't shift the UTC values by its session time zone.
            $table->dateTime('occurred_at', 6);
            $table->dateTime('created_at', 6);

            $table->index(['event', 'occurred_at']);
            $table->index(['actor_type', 'actor_id', 'occurred_at']);
            $table->index(['subject_type', 'subject_id', 'occurred_at']);
            $table->index('occurred_at');
        });
    }
};
