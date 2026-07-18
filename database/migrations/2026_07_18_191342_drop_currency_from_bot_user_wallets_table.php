<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bot_user_wallets', function (Blueprint $table) {
            $table->dropUnique(['bot_id', 'bot_user_id', 'currency']);
            $table->dropColumn('currency');
            $table->unique(['bot_id', 'bot_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_user_wallets', function (Blueprint $table) {
            $table->dropUnique(['bot_id', 'bot_user_id']);
            $table->char('currency', 3)->default('USD');
            $table->unique(['bot_id', 'bot_user_id', 'currency']);
        });
    }
};
