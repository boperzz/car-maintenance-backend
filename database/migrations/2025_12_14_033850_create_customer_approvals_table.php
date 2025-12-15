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
        Schema::create('customer_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('approval_type', ['service_modification', 'final_invoice', 'price_increase'])->default('service_modification');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('request_details')->nullable(); // What needs approval
            $table->decimal('original_amount', 10, 2)->nullable();
            $table->decimal('new_amount', 10, 2)->nullable();
            $table->text('customer_notes')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            
            $table->index('appointment_id');
            $table->index('invoice_id');
            $table->index('status');
            $table->index(['appointment_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_approvals');
    }
};
