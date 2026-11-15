<?php

return [
    'idle_credit' => [
        'label' => '💰 Unused wallet credit',
        'description' => 'Reminds members who have had credit sitting in their wallet that they can spend it.',
        'audience' => 'Members with unused wallet credit',
        'text' => "💰 You have <b>{\$wallet.balance}</b> in your wallet.\n\nYou can use it for your next purchase whenever you like.",
        'min_balance' => 'Smallest balance',
        'min_balance_description' => 'Only members with at least this much in their wallet get the reminder.',
        'idle_days' => 'Days untouched',
        'idle_days_description' => 'Only wallets that have not changed for this many days count.',
    ],
];
