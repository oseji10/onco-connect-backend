<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('questionnaire_questions', function (Blueprint $t) {
            $t->id('questionId');
            $t->unsignedBigInteger('eventId')->index();
            $t->string('type');                 // rating | single_choice | multi_choice | text
            $t->text('prompt');
            $t->json('options')->nullable();    // choices for single/multi choice
            $t->boolean('required')->default(true);
            $t->unsignedInteger('sortOrder')->default(0);
            $t->timestamps();
        });

        Schema::create('questionnaire_responses', function (Blueprint $t) {
            $t->id('responseId');
            $t->unsignedBigInteger('attendeeId')->unique(); // one response per attendee
            $t->unsignedBigInteger('eventId')->index();
            $t->json('answers');                            // { "<questionId>": value }
            $t->dateTime('submittedAt');
            $t->timestamps();
        });

        Schema::table('events', function (Blueprint $t) {
            $t->string('questionnaireStatus')->default('closed'); // open | closed
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('questionnaireStatus'));
        Schema::dropIfExists('questionnaire_responses');
        Schema::dropIfExists('questionnaire_questions');
    }
};
