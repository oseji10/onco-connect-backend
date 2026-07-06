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
    Schema::create('sub_cls', function (Blueprint $table) {
    $table->id('subClId');
    $table->unsignedBigInteger('clId');
    $table->string('state')->nullable();
    $table->string('lga')->nullable();
    $table->string('ward')->nullable();
    $table->timestamps();
    $table->unsignedBigInteger('userId')->nullable();
    $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();

    
    $table->foreign('clId')->references('clId')->on('cls')->cascadeOnDelete();
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
