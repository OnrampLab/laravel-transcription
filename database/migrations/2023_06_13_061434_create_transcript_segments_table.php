<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transcript_segments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('transcript_id')->constrained();
            $table->time('start_time');
            $table->time('end_time');
            $table->text('content');
            $table->json('words');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transcript_segments');
    }
};
