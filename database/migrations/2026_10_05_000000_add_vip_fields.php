<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Safe to re-run. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $t) {
            if (!Schema::hasColumn('attendees', 'isVip')) {
                $t->boolean('isVip')->default(false);
            }
            if (!Schema::hasColumn('attendees', 'vipGuests')) {
                $t->unsignedTinyInteger('vipGuests')->default(0); // shown to the host, not counted in headcount
            }
        });
    }

    public function down(): void
    {
        foreach (['isVip', 'vipGuests'] as $col) {
            if (Schema::hasColumn('attendees', $col)) {
                Schema::table('attendees', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
