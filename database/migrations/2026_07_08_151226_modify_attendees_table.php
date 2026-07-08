<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            // "stateOfResidence" already exists on this table — these are
            // the new columns needed for the updated registration flow.
            $table->string('country')->default('Nigeria')->after('stateOfResidence');
            $table->string('phoneCountryCode', 10)->nullable()->default('+234')->after('phoneNumber');
            $table->boolean('physicallyChallenged')->default(false)->after('country');
            $table->text('accessibilityNeeds')->nullable()->after('physicallyChallenged');
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->dropColumn(['country', 'phoneCountryCode', 'physicallyChallenged', 'accessibilityNeeds']);
        });
    }
};