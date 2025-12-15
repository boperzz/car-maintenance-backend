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
        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->string('transaction_reference')->unique(); // POS transaction reference
            $table->enum('payment_method', ['cash', 'card', 'e_wallet', 'check', 'split'])->default('cash');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->foreignId('cashier_id')->constrained('users')->onDelete('restrict'); // Staff who processed payment
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // Store additional POS data (card last 4 digits, etc.)
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_transactions');
    }
};
