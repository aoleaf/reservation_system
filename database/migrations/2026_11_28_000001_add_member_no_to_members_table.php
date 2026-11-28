<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 生徒さんに会員番号（T001 の形）を付け、メールアドレスを無くてもよくする。
 * メールの無い生徒さんは、ログインのメールが届かないだけで、予約はスタッフが代わりに入れる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('member_no', 16)->nullable()->after('id');
        });

        // もういる生徒さんには、登録の順（id の順）に番号を振る
        DB::table('members')->orderBy('id')->pluck('id')->each(function (int $id, int $i) {
            DB::table('members')->where('id', $id)->update(['member_no' => sprintf('T%03d', $i + 1)]);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('member_no', 16)->nullable(false)->change();
            $table->unique('member_no');
            $table->string('email')->nullable()->change(); // unique はそのまま（NULL はいくつあってもよい）
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['member_no']);
            $table->dropColumn('member_no');
            $table->string('email')->nullable(false)->change();
        });
    }
};
