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
        Schema::dropIfExists('pos_transactions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the table if needed (for rollback)
        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->string('transaction_reference')->unique();
            $table->enum('payment_method', ['cash', 'card', 'e_wallet', 'check', 'split'])->default('cash');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->foreignId('cashier_id')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index('transaction_reference');
            $table->index('appointment_id');
            $table->index('invoice_id');
            $table->index('status');
            $table->index('cashier_id');
            $table->index(['appointment_id', 'status']);
        });
    }
};
