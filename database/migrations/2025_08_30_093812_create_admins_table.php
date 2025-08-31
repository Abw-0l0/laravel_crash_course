<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();
            
            // Admin Specific
            $table->enum('role', ['super_admin', 'admin', 'moderator'])->default('admin');
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Two-Factor Authentication
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            
            // Activity Tracking
            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->uuid('created_by')->nullable();
            
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign Keys
            $table->foreign('created_by')->references('id')->on('admins')->onDelete('set null');
            
            // Indexes
            $table->index(['email', 'is_active']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};