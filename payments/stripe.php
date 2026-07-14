<?php
/**
 * Stripe Checkout webhook for MyAAC coin delivery.
 */

global $db, $config;

require_once '../common.php';
require_once SYSTEM . 'functions.php';
require_once SYSTEM . 'init.php';

function stripeRespond($status, $message)
{
    http_response_code($status);
    echo $message;
    exit;
}

function stripeTableExists($table)
{
    global $db;

    return (bool)$db->query('SHOW TABLES LIKE ' . $db->quote($table))->fetch();
}

function stripeEnsureTransactionsTable()
{
    global $db;

    if (stripeTableExists('myaac_stripe_transactions')) {
        return;
    }

    $db->query("CREATE TABLE `myaac_stripe_transactions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `checkout_session_id` VARCHAR(255) NOT NULL,
        `payment_intent_id` VARCHAR(255) DEFAULT NULL,
        `account_id` INT(11) UNSIGNED NOT NULL,
        `package_key` VARCHAR(80) NOT NULL,
        `coins` INT(11) NOT NULL,
        `amount_total` INT(11) NOT NULL,
        `currency` VARCHAR(10) NOT NULL,
        `coin_field` VARCHAR(40) NOT NULL,
        `status` VARCHAR(40) NOT NULL DEFAULT 'created',
        `created_at` DATETIME NOT NULL,
        `delivered_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `checkout_session_id` (`checkout_session_id`),
        KEY `account_id` (`account_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;");
}

function stripeVerifySignature($payload, $signatureHeader, $endpointSecret)
{
    if ($endpointSecret === '' || strpos($endpointSecret, 'PASTE_YOUR') !== false) {
        throw new RuntimeException('Stripe webhook secret is not configured.');
    }

    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $signatureHeader) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key === 't') {
            $timestamp = $value;
        } elseif ($key === 'v1') {
            $signatures[] = $value;
        }
    }

    if (!$timestamp || empty($signatures)) {
        throw new RuntimeException('Invalid Stripe-Signature header.');
    }

    if (abs(time() - (int)$timestamp) > 300) {
        throw new RuntimeException('Stripe webhook timestamp is outside tolerance.');
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $endpointSecret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    throw new RuntimeException('Stripe webhook signature verification failed.');
}

function stripeInsertStoreHistory($accountId, $coins, $coinField)
{
    global $db;

    $coinType = $coinField === 'coins_transferable' ? 3 : 0;
    $now = date('Y-m-d H:i:s');
    $timestamp = time();

    if (method_exists($db, 'hasTable') && $db->hasTable('coins_transactions')) {
        $db->exec(
            "INSERT INTO `coins_transactions` (`account_id`, `type`, `amount`, `description`, `timestamp`, `coin_type`) VALUES (" .
            (int)$accountId . ", 1, " . (int)$coins . ", " . $db->quote('Stripe Checkout') . ", " . $db->quote($now) . ", " . $coinType . ")"
        );
    }

    if (method_exists($db, 'hasTable') && $db->hasTable('store_history')) {
        $db->exec(
            "INSERT INTO `store_history` (`account_id`, `mode`, `description`, `coin_type`, `coin_amount`, `time`, `timestamp`, `coins`) VALUES (" .
            (int)$accountId . ", 0, " . $db->quote('Stripe Checkout') . ", " . $coinType . ", " . (int)$coins . ", " . $timestamp . ", 0, 0)"
        );
    }
}

$stripeConfig = $config['stripe'] ?? [];
if (empty($stripeConfig['enabled'])) {
    stripeRespond(404, 'Stripe is disabled.');
}

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    stripeVerifySignature($payload, $signature, $stripeConfig['webhook_secret'] ?? '');
    $event = json_decode($payload, true);
    if (!is_array($event)) {
        throw new RuntimeException('Invalid JSON payload.');
    }
} catch (Exception $e) {
    log_append('stripe_webhook_errors.log', date('Y-m-d H:i:s') . ': ' . $e->getMessage());
    stripeRespond(400, 'Invalid webhook.');
}

if (($event['type'] ?? '') !== 'checkout.session.completed') {
    stripeRespond(200, 'Ignored.');
}

$session = $event['data']['object'] ?? [];
if (($session['payment_status'] ?? '') !== 'paid') {
    stripeRespond(200, 'Unpaid session ignored.');
}

$sessionId = $session['id'] ?? '';
if ($sessionId === '') {
    stripeRespond(400, 'Missing session id.');
}

stripeEnsureTransactionsTable();

$transaction = $db->query(
    'SELECT * FROM `myaac_stripe_transactions` WHERE `checkout_session_id` = ' . $db->quote($sessionId) . ' LIMIT 1'
)->fetch();

if (!$transaction) {
    stripeRespond(404, 'Transaction not found.');
}

if (!empty($transaction['delivered_at'])) {
    stripeRespond(200, 'Already delivered.');
}

$amountTotal = (int)($session['amount_total'] ?? 0);
$currency = strtolower((string)($session['currency'] ?? ''));
if ($amountTotal !== (int)$transaction['amount_total'] || $currency !== strtolower($transaction['currency'])) {
    stripeRespond(400, 'Amount mismatch.');
}

$coinField = $transaction['coin_field'];
if (!in_array($coinField, ['coins', 'coins_transferable'], true)) {
    stripeRespond(400, 'Invalid coin field.');
}

$accountId = (int)$transaction['account_id'];
$coins = (int)$transaction['coins'];
$paymentIntent = $session['payment_intent'] ?? null;

$db->beginTransaction();
try {
    $db->exec('UPDATE `accounts` SET `' . $coinField . '` = `' . $coinField . '` + ' . $coins . ' WHERE `id` = ' . $accountId);
    $db->exec(
        'UPDATE `myaac_stripe_transactions` SET `payment_intent_id` = ' . $db->quote($paymentIntent) .
        ', `status` = ' . $db->quote('paid') .
        ', `delivered_at` = NOW() WHERE `checkout_session_id` = ' . $db->quote($sessionId)
    );
    stripeInsertStoreHistory($accountId, $coins, $coinField);
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    log_append('stripe_webhook_errors.log', date('Y-m-d H:i:s') . ': ' . $e->getMessage());
    stripeRespond(500, 'Delivery failed.');
}

stripeRespond(200, 'Delivered.');
