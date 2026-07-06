<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->boolean('isAccredited')->default(false)->after('uniqueId');
            $table->timestamp('accreditedAt')->nullable()->after('isAccredited');
            $table->unsignedBigInteger('accreditedBy')->nullable()->after('accreditedAt');

            // FK to the staff user who accredited them. Drop this block if your
            // users primary key isn't `id`.
            $table->foreign('accreditedBy')
                  ->references('id')->on('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->dropForeign(['accreditedBy']);
            $table->dropColumn(['isAccredited', 'accreditedAt', 'accreditedBy']);
        });
    }
};