<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The create migration for by_wallet_attempts was later edited in place to add bot_id
 * and bot_user_id, so databases that had already run it never got the columns and
 * paying from the wallet failed with "Unknown column 'bot_id'". This adds them the way
 * a fresh install has them (and does nothing on one), filling existing attempts in
 * from the invoice they paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('by_wallet_attempts')) {
            return;
        }

        $needsBot = ! Schema::hasColumn('by_wallet_attempts', 'bot_id');
        $needsUser = ! Schema::hasColumn('by_wallet_attempts', 'bot_user_id');

        if (! $needsBot && ! $needsUser) {
            return;
        }

        Schema::table('by_wallet_attempts', function (Blueprint $table) use ($needsBot, $needsUser) {
            if ($needsBot) {
                $table->unsignedBigInteger('bot_id')->nullable()->after('id');
            }
            if ($needsUser) {
                $table->unsignedBigInteger('bot_user_id')->nullable()->after('bot_id');
            }
        });

        $this->backfill();

        Schema::table('by_wallet_attempts', function (Blueprint $table) use ($needsBot, $needsUser) {
            if ($needsBot) {
                $table->unsignedBigInteger('bot_id')->nullable(false)->change();
                $table->foreign('bot_id')->references('id')->on('bots');
            }
            if ($needsUser) {
                $table->foreign('bot_user_id')->references('id')->on('bot_users');
            }
        });
    }

    public function down(): void
    {
        // Not reversible on purpose: the columns are part of the current schema.
    }

    /** The invoice an attempt paid knows its bot and member; an orphan gets the first bot. */
    private function backfill(): void
    {
        $fallbackBot = DB::table('bots')->orderBy('id')->value('id');

        DB::table('by_wallet_attempts')->orderBy('id')->each(function (object $attempt) use ($fallbackBot) {
            $invoice = DB::table('invoices')
                ->where('payment_attempt_type', 'like', '%ByWalletAttempt')
                ->where('payment_attempt_id', $attempt->id)
                ->first(['bot_id', 'bot_user_id']);

            DB::table('by_wallet_attempts')->where('id', $attempt->id)->update([
                'bot_id' => $invoice->bot_id ?? $fallbackBot,
                'bot_user_id' => $invoice->bot_user_id ?? null,
            ]);
        });
    }
};
