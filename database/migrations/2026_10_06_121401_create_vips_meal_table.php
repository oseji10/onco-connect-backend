<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $t) {
            // NULL = the VIP themself. Set = a guest pass belonging to that VIP.
            $t->unsignedBigInteger('vipHostId')->nullable()->index();
            $t->foreign('vipHostId')->references('attendeeId')->on('attendees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $t) {
            $t->dropForeign(['vipHostId']);
            $t->dropColumn('vipHostId');
        });
    }
};

/*
 * Also add this relationship to App\Models\Attendee:
 *
 * public function guests()
 * {
 *     return $this->hasMany(self::class, 'vipHostId', 'attendeeId')->orderBy('attendeeId');
 * }
 */