<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique(); // 予約番号 'YYMM-XXXXXX'
            $table->foreignId('slot_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16); // trial / monthly
            $table->string('channel', 16); // web / phone / dm / walkin / member / staff
            $table->unsignedTinyInteger('party_size');
            $table->string('status', 16); // pending / confirmed / canceled / expired / attended / no_show
            $table->unsignedInteger('amount');
            $table->unsignedInteger('refunded_amount')->nullable();
            $table->string('refund_status', 16)->default('none'); // none / pending / succeeded / failed
            $table->dateTime('expires_at')->nullable(); // pending の期限
            $table->char('cancel_token_hash', 64)->nullable()->unique(); // キャンセル用リンクのトークンの SHA-256
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('contact_email')->nullable();
            $table->foreignId('member_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->dateTime('canceled_at')->nullable();
            $table->string('canceled_by', 16)->nullable(); // customer / staff / system
            $table->timestamps();

            $table->index(['slot_id', 'status']);
        });

        // Stripe の webhook を2回処理しないための記録
        Schema::create('stripe_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type');
            $table->dateTime('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('reservations');
    }
};
