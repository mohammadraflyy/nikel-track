<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsageLogsTable extends Migration
{
    public function up()
    {
        Schema::create('usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->integer('start_km'); 
            $table->integer('end_km');
            $table->decimal('fuel_used', 8, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('booking_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('usage_logs');
    }
}