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
        Schema::table('attendees', function (Blueprint $table) {
    $table->unsignedBigInteger('userId')->nullable()->after('attendeeId');
    $table->foreign('userId')->references('id')->on('users')->onDelete('set null');
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
