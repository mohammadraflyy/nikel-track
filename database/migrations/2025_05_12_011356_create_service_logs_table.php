<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServiceLogsTable extends Migration
{
    public function up()
    {
        Schema::create('service_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->date('service_date'); 
            $table->string('service_type'); 
            $table->text('description')->nullable(); 
            $table->decimal('cost', 10, 2);
            $table->timestamps();
            
            $table->index('vehicle_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_logs');
    }
}
