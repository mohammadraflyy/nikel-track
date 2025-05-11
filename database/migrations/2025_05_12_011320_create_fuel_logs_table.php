<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFuelLogsTable extends Migration
{
    public function up()
    {
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->decimal('amount', 8, 2);
            $table->date('log_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('vehicle_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('fuel_logs');
    }
}