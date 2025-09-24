<?php

use TelegramBotEssentials\Essence\Models\Billing\Payment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('by_wallet_attempts', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 65, 30);
            $table->timestamp('received_at')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('by_wallet_attempts');
    }
};
