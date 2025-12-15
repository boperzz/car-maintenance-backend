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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('staff_id')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('appointment_date');
            $table->dateTime('end_time')->nullable(); // Calculated based on services
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable(); // Customer notes
            $table->text('staff_notes')->nullable(); // Staff findings/recommendations
            $table->text('service_results')->nullable(); // Service completion details
            $table->decimal('total_price', 10, 2)->default(0);
            $table->timestamps();
            
            $table->index(['appointment_date', 'status']);
            $table->index('staff_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
