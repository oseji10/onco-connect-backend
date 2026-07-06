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
    Schema::create('wima_core', function (Blueprint $table) {
    $table->id('coreId');
    $table->string('portfolio')->nullable();

    $table->string('state')->nullable();
    $table->string('lga')->nullable();
    $table->timestamps();
    $table->unsignedBigInteger('userId')->nullable();
    $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
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
