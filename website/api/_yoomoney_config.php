<?php
// YooMoney (ЮMoney, ex-Яндекс.Деньги) configuration.
// Wallet/secret are loaded from secrets.php

require_once __DIR__ . '/secrets.php';

// YOOMONEY_WALLET and YOOMONEY_NOTIFICATION_SECRET come from secrets.php

if (!defined('YOOMONEY_QUICKPAY_URL')) {
    define('YOOMONEY_QUICKPAY_URL', 'https://yoomoney.ru/quickpay/confirm.xml');
}
if (!defined('YOOMONEY_SUCCESS_URL')) {
    define('YOOMONEY_SUCCESS_URL', 'https://scarlex.ru/dashboard.html?topup=success');
}
