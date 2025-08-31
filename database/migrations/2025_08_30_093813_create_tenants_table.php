<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->unique()->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            
            // Business Information
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->json('business_details')->nullable(); // address, tax_id, etc.
            
            // Subscription & Status
            $table->enum('status', ['active', 'inactive', 'suspended', 'trial'])->default('trial');
            $table->string('subscription_plan')->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            
            // Limits & Configuration
            $table->integer('user_limit')->default(5);
            $table->integer('storage_limit')->default(1024); // MB
            $table->json('features')->nullable(); // enabled features
            $table->json('settings')->nullable(); // tenant-specific settings
            
            // Database Configuration
            $table->string('database_name')->nullable();
            $table->string('database_username')->nullable();
            $table->string('database_password')->nullable();
            
            // Owner Information
            $table->uuid('owner_id')->nullable();
            $table->string('owner_email');
            $table->string('owner_name');
            
            // Timestamps
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['slug', 'status']);
            $table->index('domain');
            $table->index('subscription_expires_at');
            $table->index('owner_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};