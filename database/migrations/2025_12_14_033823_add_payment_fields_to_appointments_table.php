<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Payment fields
            $table->string('job_order_number')->unique()->nullable()->after('id'); // Unique Job Order ID
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid')->after('total_price');
            $table->enum('payment_method', ['POS', 'cash', 'card', 'e_wallet', 'check', 'online'])->nullable()->after('payment_status');
            $table->decimal('amount_paid', 10, 2)->default(0)->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('amount_paid');
            
            // Indexes for performance
            $table->index('job_order_number');
            $table->index('payment_status');
            $table->index(['status', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Drop indexes first
            $indexes = [
                'appointments_job_order_number_index',
                'appointments_payment_status_index',
                'appointments_status_payment_status_index'
            ];
            
            foreach ($indexes as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Exception $e) {
                    // Index might not exist, continue
                }
            }
            
            // Drop columns
            $table->dropColumn([
                'job_order_number',
                'payment_status',
                'payment_method',
                'amount_paid',
                'paid_at'
            ]);
        });
        
        // Revert status enum
        try {
            DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending'");
        } catch (\Exception $e) {
            // Status enum might already be reverted
        }
    }
};
