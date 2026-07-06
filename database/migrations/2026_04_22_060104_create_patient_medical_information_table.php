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
        Schema::create('participant_medical_info', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendeeId')->unique();
            
            // General Allergies
            $table->boolean('has_allergy')->default(false);
            $table->text('allergy_details')->nullable();
            
            // Drug Allergies
            $table->boolean('has_drug_allergy')->default(false);
            $table->string('drug_allergy_type')->nullable();
            
            // Pregnancy
            $table->boolean('is_pregnant')->default(false);
            $table->string('pregnancy_months')->nullable();
            
            // Breastfeeding
            $table->boolean('is_breastfeeding')->default(false);
            
            // Current Medications
            $table->boolean('on_medications')->default(false);
            $table->text('medication_type')->nullable();
            
            // Birth Control
            $table->boolean('on_birth_control')->default(false);
            
            // Surgical History
            $table->boolean('has_surgical_history')->default(false);
            $table->text('surgical_history_details')->nullable();
            
            // Medical Conditions
            $table->boolean('has_medical_conditions')->default(false);
            $table->text('medical_conditions_details')->nullable();
            
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('attendeeId')
                  ->references('attendeeId')
                  ->on('attendees')
                  ->onDelete('cascade');
                  
            // Index for faster lookups
            $table->index('attendeeId');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participant_medical_info');
    }
};