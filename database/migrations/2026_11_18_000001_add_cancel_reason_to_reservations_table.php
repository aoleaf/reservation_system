<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // キャンセルの理由：customer（お客様の都合・規定どおり返金）／ shop（お店の都合・全額返金）
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('cancel_reason', 16)->nullable()->after('canceled_by');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('cancel_reason');
        });
    }
};
