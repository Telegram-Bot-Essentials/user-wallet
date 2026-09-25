<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function runTenantColumnsMigration(): void
{
    (require dirname(__DIR__, 2).'/database/migrations/2026_09_26_000000_add_tenant_columns_to_by_wallet_attempts_table.php')->up();
}

/** The table as the package first created it, before attempts belonged to a bot. */
function oldByWalletAttempts(): void
{
    Schema::dropIfExists('by_wallet_attempts');
    Schema::create('by_wallet_attempts', function (Blueprint $table) {
        $table->id();
        $table->decimal('amount', 65, 30);
        $table->timestamp('received_at')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });
}

it('adds bot_id and bot_user_id to an older table and fills them from the invoices', function () {
    $bot = $this->makeBot();
    $user = $this->makeBotUser($bot, 4242);
    oldByWalletAttempts();

    $paid = DB::table('by_wallet_attempts')->insertGetId(['amount' => 100, 'created_at' => now(), 'updated_at' => now()]);
    $orphan = DB::table('by_wallet_attempts')->insertGetId(['amount' => 5, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('invoices')->insert([
        'bot_id' => $bot->id, 'bot_user_id' => $user->id, 'payable_type' => 'x', 'payable_id' => 1,
        'price' => 100, 'original_price' => 100, 'public_token' => 'tok',
        'payment_attempt_type' => 'TelegramBotEssentials\\UserWallet\\Models\\ByWalletAttempt', 'payment_attempt_id' => $paid,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    runTenantColumnsMigration();

    expect(Schema::hasColumns('by_wallet_attempts', ['bot_id', 'bot_user_id']))->toBeTrue()
        ->and(DB::table('by_wallet_attempts')->find($paid)->bot_id)->toBe($bot->id)
        ->and(DB::table('by_wallet_attempts')->find($paid)->bot_user_id)->toBe($user->id)
        ->and(DB::table('by_wallet_attempts')->find($orphan)->bot_id)->toBe($bot->id)
        ->and(DB::table('by_wallet_attempts')->find($orphan)->bot_user_id)->toBeNull();
});

it('does nothing on a database that already has the columns', function () {
    $before = Schema::getColumnListing('by_wallet_attempts');

    runTenantColumnsMigration();

    expect(Schema::getColumnListing('by_wallet_attempts'))->toBe($before);
});
