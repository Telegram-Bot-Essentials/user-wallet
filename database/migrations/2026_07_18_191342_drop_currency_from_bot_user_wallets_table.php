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
            // bot_id's foreign key relies on an index covering it as a leftmost column;
            // the new unique must exist before the old composite one is dropped.
            $table->unique(['bot_id', 'bot_user_id']);
            $table->dropUnique(['bot_id', 'bot_user_id', 'currency']);
            $table->dropColumn('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_user_wallets', function (Blueprint $table) {
            $table->char('currency', 3)->default('USD');
            $table->unique(['bot_id', 'bot_user_id', 'currency']);
            $table->dropUnique(['bot_id', 'bot_user_id']);
        });
    }
};
