<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // ログイン用
            $table->string('name');
            $table->string('phone', 32);
            $table->string('email')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // 生徒ごと・月ごとの提出と日程変更
        Schema::create('member_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->char('month', 7); // 'YYYY-MM'
            $table->dateTime('submitted_at')->nullable();
            $table->boolean('change_used')->default(false);
            $table->timestamps();

            $table->unique(['member_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_months');
        Schema::dropIfExists('members');
    }
};
