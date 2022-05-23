<?php

return [
    'frontend' => [
        'extcode/cart-payrexx/webhook' => [
            'target' => \Extcode\CartPayrexx\Middleware\Webhook::class,
            'after' => [
                'typo3/cms-frontend/tsfe',
            ],
            'before' => [
                'typo3/cms-frontend/typo3/cms-frontend/prepare-tsfe-rendering',
            ],
        ],
    ],
];
