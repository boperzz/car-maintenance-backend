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
        Schema::create('service_modifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->onDelete('cascade');
            $table->foreignId('modified_by')->constrained('users')->onDelete('restrict'); // Staff who made modification
            $table->enum('modification_type', ['add_service', 'remove_service', 'add_labor', 'add_part', 'adjust_price', 'discount'])->default('add_service');
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->foreignId('service_type_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['pending_approval', 'approved', 'rejected'])->default('pending_approval');
            $table->text('reason')->nullable(); // Why modification was made
            $table->timestamps();
            
            $table->index('appointment_id');
            $table->index('modified_by');
            $table->index('status');
            $table->index(['appointment_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_modifications');
    }
};
