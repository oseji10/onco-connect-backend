<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id('facilityId');
            $table->string('facilityName');
            $table->string('facilityCode')->unique();
            $table->string('facilityState')->nullable();
            $table->string('facilityLga')->nullable();
            $table->string('facilityAddress')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};