<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendees', function (Blueprint $table) {
            $table->id('attendeeId');
            $table->unsignedBigInteger('eventId');
            $table->string('uniqueId')->nullable()->index();
            $table->string('firstName')->nullable();
            $table->string('lastName')->nullable();
            $table->string('otherNames')->nullable();
            $table->string('maritalStatus')->nullable();
            $table->string('phoneNumber')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('organizationName')->nullable();
            $table->string('gender')->nullable();
            $table->string('category')->nullable();
            $table->string('stateOfResidence')->nullable();
          
            $table->string('title')->nullable();
            $table->string('participationType')->nullable();
            
            $table->string('photoUrl')->nullable();
            $table->string('accomodation')->nullable();
            $table->string('color')->nullable();
            $table->boolean('isRegistered')->default(false);
            $table->timestamp('registeredAt')->nullable();
            $table->unsignedBigInteger('registeredBy')->nullable();
            $table->timestamps();

            $table->foreign('eventId')->references('eventId')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};