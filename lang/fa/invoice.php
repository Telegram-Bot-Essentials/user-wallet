<?php

return [
    'by_wallet' => [
        'answers' => [
            'creditIsNotEnough' => '⛔️ موجودی کیف پول شما برای پرداخت این فاکتور کافی نیست.'
                ."\r\nموجودی شما: :credit"
                ."\r\nمبلغ مورد نیاز: :neededCredit",
        ],
        'keys' => [
            'pay' => 'پرداخت با کیف پول 💰 - :price',
        ],
    ],
    'locks' => [
        'user_payment' => [
            'accepted' => '✅ پرداخت با کیف پول تأیید شد',
        ],
    ],
];
