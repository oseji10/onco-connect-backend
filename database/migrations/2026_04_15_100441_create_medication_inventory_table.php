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
        // Medication Supplies Table (inventory from pharmacy/vendors)
        Schema::create('medication_supplies', function (Blueprint $table) {
            $table->id('supplyId');
            $table->unsignedBigInteger('eventId');
            $table->string('drugName');
            $table->string('batchNumber');
            $table->date('expiryDate');
            $table->integer('quantitySupplied');
            $table->integer('quantityDispensed')->default(0);
            $table->integer('quantityRemaining');
            $table->date('supplyDate');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recordedBy')->nullable(); // User who recorded the supply
            $table->timestamps();

            $table->foreign('eventId')->references('eventId')->on('events')->onDelete('cascade');
            $table->foreign('recordedBy')->references('id')->on('users')->onDelete('set null');

            $table->index(['eventId', 'drugName']);
            $table->index('expiryDate');
            $table->index('created_at');
        });

        // Medication Dispensing Table (tracks each dispensing to participants)
        Schema::create('medication_dispensing', function (Blueprint $table) {
            $table->id('dispensingId');
            $table->unsignedBigInteger('eventId');
            $table->unsignedBigInteger('attendeeId')->nullable(); // Nullable for non-participants
            $table->unsignedBigInteger('supplyId'); // Which batch was used
            $table->string('drugName');
            $table->integer('quantityDispensed');
            
            // Non-participant details (for walk-ins, staff, visitors)
            $table->string('recipientName')->nullable(); // Name if not a participant
            $table->string('recipientType')->nullable(); // 'participant', 'staff', 'visitor', 'other'
            $table->text('recipientNotes')->nullable(); // Additional info about recipient
            
            $table->text('symptoms')->nullable(); // Why medication was given
            $table->text('instructions')->nullable(); // Dosage instructions
            $table->unsignedBigInteger('dispensedBy')->nullable(); // Nurse who dispensed
            $table->string('deviceName')->nullable();
            $table->timestamps();

            $table->foreign('eventId')->references('eventId')->on('events')->onDelete('cascade');
            $table->foreign('attendeeId')->references('attendeeId')->on('attendees')->onDelete('cascade');
            $table->foreign('supplyId')->references('supplyId')->on('medication_supplies')->onDelete('cascade');
            $table->foreign('dispensedBy')->references('id')->on('users')->onDelete('set null');

            $table->index(['eventId', 'attendeeId']);
            $table->index(['eventId', 'drugName']);
            $table->index(['eventId', 'recipientType']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_dispensing');
        Schema::dropIfExists('medication_supplies');
    }
};