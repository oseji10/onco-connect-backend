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
       // colors table migration
Schema::create('colors', function (Blueprint $table) {
    $table->id('colorId');
    $table->unsignedBigInteger('eventId')->nullable();
    $table->string('colorName'); // e.g., 'Red', 'Blue', etc.
    $table->string('hexCode')->nullable();
    $table->integer('capacity')->default(34); // CL manages ~34 participants
    // $table->foreignId('communityLeaderId')->nullable()->constrained('users');
    $table->unsignedBigInteger('communityLeaderId')->nullable();
    $table->foreign('communityLeaderId')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('eventId')->references('eventId')->on('events')->cascadeOnDelete();
    $table->timestamps();
});

// sub_community_leaders table migration
Schema::table('sub_cls', function (Blueprint $table) {
    // $table->foreignId('eventId')->constrained('events');
    // $table->foreignId('userId')->constrained('users'); // The actual SubCL user
    // $table->timestamps();
    // $table->foreignId('colorId')->constrained('colors');
    // $table->id();
    $table->unsignedBigInteger('eventId')->nullable();
    $table->foreign('eventId')->references('eventId')->on('events')->cascadeOnDelete();
    $table->unsignedBigInteger('colorId')->nullable();
    $table->foreign('colorId')->references('colorId')->on('colors')->cascadeOnDelete();
    $table->integer('maxCapacity')->default(13);
});

// attendees table - add these columns
Schema::table('attendees', function (Blueprint $table) {
    $table->unsignedBigInteger('colorId')->nullable();
    $table->foreign('colorId')->references('colorId')->on('colors')->cascadeOnDelete();

});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colors');
    }
};
