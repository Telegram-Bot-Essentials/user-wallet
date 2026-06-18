<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Essence\Models\BotUser;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('bot_users') || !Schema::hasColumn('bot_users', 'balance')) {
            return;
        }

        if (!Schema::hasTable('bot_user_wallets')) {
            Schema::create('bot_user_wallets', function (Blueprint $table) {
                $table->id();
                $table->foreignIdFor(Bot::class)->constrained();
                $table->foreignIdFor(BotUser::class)->constrained();
                $table->decimal('balance', 65, 30)->default(0);
                $table->char('currency', 3);
                $table->timestamps();

                $table->unique(['bot_id', 'bot_user_id', 'currency']);
            });
        }

        $botCurrencies = $this->resolveBotCurrencies();

        DB::table('bot_users')
            ->select(['id', 'bot_id', 'balance'])
            ->orderBy('id')
            ->each(function ($botUser) use ($botCurrencies) {
                $currency = $botCurrencies[$botUser->bot_id] ?? 'USD';

                $exists = DB::table('bot_user_wallets')
                    ->where('bot_id', $botUser->bot_id)
                    ->where('bot_user_id', $botUser->id)
                    ->where('currency', $currency)
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('bot_user_wallets')->insert([
                    'bot_id' => $botUser->bot_id,
                    'bot_user_id' => $botUser->id,
                    'balance' => $botUser->balance,
                    'currency' => $currency,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('bot_users', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('bot_users') || Schema::hasColumn('bot_users', 'balance')) {
            return;
        }

        Schema::table('bot_users', function (Blueprint $table) {
            $table->decimal('balance', 65, 30)->default(0)->after('power');
        });

        if (!Schema::hasTable('bot_user_wallets')) {
            return;
        }

        DB::table('bot_user_wallets')
            ->select(['bot_user_id', 'balance'])
            ->orderBy('id')
            ->each(function ($wallet) {
                DB::table('bot_users')
                    ->where('id', $wallet->bot_user_id)
                    ->update(['balance' => $wallet->balance]);
            });
    }

    /**
     * @return array<int, string>
     */
    private function resolveBotCurrencies(): array
    {
        $currencies = [];

        if (Schema::hasTable('bot_settings') && Schema::hasColumn('bot_settings', 'key')) {
            $settingsCurrencies = DB::table('bot_settings')
                ->where('key', 'billing.currency')
                ->pluck('value', 'bot_id');

            foreach ($settingsCurrencies as $botId => $currency) {
                if ($currency !== null && $currency !== '') {
                    $currencies[$botId] = $currency;
                }
            }

            $walletCurrencies = DB::table('bot_settings')
                ->where('key', 'billing.user_wallet.currency')
                ->pluck('value', 'bot_id');

            foreach ($walletCurrencies as $botId => $currency) {
                if ($currency !== null && $currency !== '') {
                    $currencies[$botId] = $currency;
                }
            }
        }

        if (Schema::hasTable('bots')) {
            $select = ['id', 'data'];
            if (Schema::hasColumn('bots', 'currency')) {
                $select[] = 'currency';
            }

            DB::table('bots')
                ->select($select)
                ->orderBy('id')
                ->each(function ($bot) use (&$currencies) {
                    if (isset($currencies[$bot->id])) {
                        return;
                    }

                    if (isset($bot->currency) && $bot->currency !== null && $bot->currency !== '') {
                        $currencies[$bot->id] = $bot->currency;
                        return;
                    }

                    $data = json_decode($bot->data ?? '{}', true);
                    if (is_array($data) && !empty($data['currency'])) {
                        $currencies[$bot->id] = $data['currency'];
                    }
                });
        }

        return $currencies;
    }
};
