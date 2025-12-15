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
        // Add columns only if they don't exist
        if (!Schema::hasColumn('appointments', 'job_order_number')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->string('job_order_number')->unique()->nullable()->after('id');
            });
        }
        
        if (!Schema::hasColumn('appointments', 'payment_status')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid')->after('total_price');
            });
        }
        
        if (!Schema::hasColumn('appointments', 'payment_method')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->enum('payment_method', ['POS', 'cash', 'card', 'e_wallet', 'check', 'online'])->nullable()->after('payment_status');
            });
        }
        
        if (!Schema::hasColumn('appointments', 'amount_paid')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->decimal('amount_paid', 10, 2)->default(0)->after('payment_method');
            });
        }
        
        if (!Schema::hasColumn('appointments', 'paid_at')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->timestamp('paid_at')->nullable()->after('amount_paid');
            });
        }
        
        // Add indexes if they don't exist
        if (Schema::hasColumn('appointments', 'job_order_number')) {
            try {
                $indexes = DB::select("SHOW INDEXES FROM appointments WHERE Key_name = 'appointments_job_order_number_index'");
                if (empty($indexes)) {
                    Schema::table('appointments', function (Blueprint $table) {
                        $table->index('job_order_number');
                    });
                }
            } catch (\Exception $e) {
                // Index might already exist
            }
        }
        
        if (Schema::hasColumn('appointments', 'payment_status')) {
            try {
                $indexes = DB::select("SHOW INDEXES FROM appointments WHERE Key_name = 'appointments_payment_status_index'");
                if (empty($indexes)) {
                    Schema::table('appointments', function (Blueprint $table) {
                        $table->index('payment_status');
                    });
                }
                
                $indexes = DB::select("SHOW INDEXES FROM appointments WHERE Key_name = 'appointments_status_payment_status_index'");
                if (empty($indexes)) {
                    Schema::table('appointments', function (Blueprint $table) {
                        $table->index(['status', 'payment_status']);
                    });
                }
            } catch (\Exception $e) {
                // Indexes might already exist
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't drop columns in down() - let the original migration handle it
    }
};
