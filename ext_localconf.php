<?php

defined('TYPO3_MODE') or die();

// configure plugins

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'CartPayrexx',
    'Cart',
    [
        \Extcode\CartPayrexx\Controller\Order\PaymentController::class => 'success, cancel',
    ],
    [
        \Extcode\CartPayrexx\Controller\Order\PaymentController::class => 'success, cancel',
    ]
);
