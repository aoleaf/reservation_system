<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('capacity');
            $table->boolean('trial_open'); // 土日だけ true
            $table->unsignedTinyInteger('monthly_limit')->nullable(); // 土日は上限あり、平日は null
            $table->string('status', 16)->default('open'); // open / paused / closed
            $table->timestamps();

            $table->unique(['date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};
