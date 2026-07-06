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
    Schema::create('incident_updates', function (Blueprint $table) {
    $table->id('updateId');
    $table->unsignedBigInteger('incidentId');
    $table->unsignedBigInteger('updatedBy');
    $table->string('oldStatus')->nullable();
    $table->string('newStatus')->nullable();
    $table->text('note');
    $table->timestamps();

    $table->foreign('incidentId')->references('incidentId')->on('incidents')->cascadeOnDelete();
    $table->foreign('updatedBy')->references('id')->on('users')->cascadeOnDelete();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
