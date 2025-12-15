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
        Schema::create('shop_blackout_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('reason')->nullable(); // Holiday, Maintenance, etc.
            $table->text('description')->nullable();
            $table->boolean('is_recurring')->default(false); // For annual holidays
            $table->timestamps();
            
            $table->unique('date');
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_blackout_dates');
    }
};
