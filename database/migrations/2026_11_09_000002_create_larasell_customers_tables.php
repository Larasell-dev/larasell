<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number')->unique();
            $table->string('type');
            $table->string('status')->index();
            $table->string('source');
            $table->string('preferred_locale')->nullable();
            $table->string('preferred_currency', 3)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('larasell_customer_person_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('larasell_customers')->cascadeOnDelete();
            $table->string('salutation')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('larasell_customer_company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('larasell_customers')->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('legal_form')->nullable();
            $table->string('company_number')->nullable();
            $table->string('vat_id')->nullable();
            $table->string('country_code', 2);
            $table->string('industry')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });

        Schema::create('larasell_customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('larasell_customers')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->timestamps();
        });

        Schema::create('larasell_customer_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('larasell_customers')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('larasell_customer_contacts')->nullOnDelete();
            $table->morphs('account');
            $table->timestamps();
            $table->unique(['customer_id', 'account_type', 'account_id']);
        });

        Schema::create('larasell_customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('larasell_customers')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('company')->nullable();
            $table->json('street');
            $table->string('postcode');
            $table->string('city');
            $table->string('region')->nullable();
            $table->string('country_code', 2);
            $table->string('phone')->nullable();
            $table->boolean('is_default_billing')->default(false);
            $table->boolean('is_default_shipping')->default(false);
            $table->timestamps();
        });

        Schema::create('larasell_customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->json('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('larasell_customer_group_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('larasell_customers')->cascadeOnDelete();
            $table->foreignId('customer_group_id')->constrained('larasell_customer_groups')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'customer_group_id']);
        });

        Schema::create('larasell_customer_number_sequences', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('number');
        });
    }
};
