<?php

return [
    'by_wallet' => [
        'answers' => [
            'creditIsNotEnough' => '⛔️ You don\'t have enough wallet balance to pay this invoice.'
                ."\r\nYour balance: :credit"
                ."\r\nRequired: :neededCredit",
        ],
        'keys' => [
            'pay' => 'Pay with wallet 💰 - :price',
        ],
    ],
    'locks' => [
        'user_payment' => [
            'accepted' => '✅ Payment accepted via wallet',
        ],
    ],
];
