<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $t) {
            $t->string('questionnaireToken', 64)->nullable()->unique(); // personal, unguessable link token
            $t->dateTime('questionnaireInvitedAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendees', fn (Blueprint $t) => $t->dropColumn(['questionnaireToken', 'questionnaireInvitedAt']));
    }
};