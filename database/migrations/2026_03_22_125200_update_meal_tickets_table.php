<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('meal_tickets', function (Blueprint $table) {
            $table->string('qrPath')->nullable()->after('qrPayload');
            $table->string('qrUrl')->nullable()->after('qrPath');
        });
    }

    public function down(): void
    {
        Schema::table('meal_tickets', function (Blueprint $table) {
            $table->dropColumn(['qrPath', 'qrUrl']);
        });
    }
};