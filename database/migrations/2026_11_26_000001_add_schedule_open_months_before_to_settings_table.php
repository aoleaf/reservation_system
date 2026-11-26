<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 日程の受付を何か月前に始めるか（森下様の回答 Q3：毎月1日に翌々月の受付を始める → 2）
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('schedule_open_months_before')->default(2)->after('schedule_open_day');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('schedule_open_months_before');
        });
    }
};
