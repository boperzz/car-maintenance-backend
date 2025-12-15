<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add username field (unique) - will be used for login instead of email
            $table->string('username')->unique()->nullable()->after('name');
            
            // Split name into first_name (keeping name for now), last_name, middle_name
            $table->string('last_name')->nullable()->after('name');
            $table->string('middle_name')->nullable()->after('last_name');
            
            // Add profile picture
            $table->string('profile_picture')->nullable()->after('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'last_name', 'middle_name', 'profile_picture']);
        });
    }
};
