<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_passes', function (Blueprint $table) {
            $table->unsignedBigInteger('attendeeId')->nullable()->after('eventId');
            // $table->string('serialNumber')->unique()->after('attendeeId');
            $table->boolean('isAssigned')->default(false)->after('serialNumber');
            $table->timestamp('assignedAt')->nullable()->after('isAssigned');
            $table->unsignedBigInteger('assignedBy')->nullable()->after('assignedAt');

            $table->foreign('attendeeId')
                ->references('attendeeId')
                ->on('attendees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_passes', function (Blueprint $table) {
            $table->dropForeign(['attendeeId']);
            $table->dropColumn([
                'attendeeId',
                // 'serialNumber',
                'isAssigned',
                'assignedAt',
                'assignedBy',
            ]);
        });
    }
};