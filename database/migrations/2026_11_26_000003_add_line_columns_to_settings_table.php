<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // LINE 通知：送り先のグループ（スタッフが設定画面で決める）・候補（公式アカウントを招待したグループ）・朝のまとめを送った日時
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('line_group_id')->nullable();
            $table->string('line_group_candidate_id')->nullable();
            $table->dateTime('line_group_candidate_at')->nullable();
            $table->dateTime('line_digest_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['line_group_id', 'line_group_candidate_id', 'line_group_candidate_at', 'line_digest_sent_at']);
        });
    }
};
