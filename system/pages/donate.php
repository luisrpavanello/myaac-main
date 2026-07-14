<?php
global $config, $twig, $logged, $account_logged, $db, $title, $action;

defined('MYAAC') or die('Direct access not allowed!');

$title = 'Payment Center';

require_once PLUGINS . 'pagseguro/config.php';

$defaults = [
    'currency' => 'USD',
    'support_contact' => 'support@numenor.global',
    'stripe_checkout_url' => '',
    'bank_transfer_enabled' => false,
    'bank_transfer_instructions' => 'Contact support before sending a bank transfer.',
    'coins_packages' => $config['pagSeguro']['donates'] ?? [],
    'premium_packages' => [
        'premium_30' => ['label' => '30 Days Premium', 'days' => 30, 'price' => 9.99],
        'premium_90' => ['label' => '90 Days Premium', 'days' => 90, 'price' => 24.99],
    ],
    'crypto_wallets' => [],
];
$paymentCenter = array_replace_recursive($defaults, $config['payment_center'] ?? []);
$twig->addGlobal('config', $config);

function paymentCenterTableExists($table)
{
    global $db;

    return (bool)$db->query('SHOW TABLES LIKE ' . $db->quote($table))->fetch();
}

function paymentCenterEnsureTable()
{
    global $db;

    if (paymentCenterTableExists('myaac_payment_requests')) {
        if (!paymentCenterTableExists('myaac_stripe_transactions')) {
            paymentCenterEnsureStripeTable();
        }
        return;
    }

    $db->query("CREATE TABLE `myaac_payment_requests` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `reference` VARCHAR(40) NOT NULL,
        `account_id` INT(11) UNSIGNED NOT NULL,
        `product_type` VARCHAR(20) NOT NULL,
        `package_key` VARCHAR(80) NOT NULL,
        `package_label` VARCHAR(120) NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `currency` VARCHAR(10) NOT NULL,
        `payment_method` VARCHAR(30) NOT NULL,
        `payment_network` VARCHAR(40) DEFAULT NULL,
        `transaction_id` VARCHAR(190) DEFAULT NULL,
        `payer_note` TEXT DEFAULT NULL,
        `status` VARCHAR(30) NOT NULL DEFAULT 'manual_review',
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `reference` (`reference`),
        KEY `account_id` (`account_id`),
        KEY `status` (`status`),
        KEY `product_type` (`product_type`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;");

    paymentCenterEnsureStripeTable();
}

function paymentCenterEnsureStripeTable()
{
    global $db;

    if (paymentCenterTableExists('myaac_stripe_transactions')) {
        paymentCenterEnsureStripeColumns();
        return;
    }

    $db->query("CREATE TABLE `myaac_stripe_transactions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `checkout_session_id` VARCHAR(255) NOT NULL,
        `payment_intent_id` VARCHAR(255) DEFAULT NULL,
        `account_id` INT(11) UNSIGNED NOT NULL,
        `product_type` VARCHAR(20) NOT NULL DEFAULT 'coins',
        `package_key` VARCHAR(80) NOT NULL,
        `coins` INT(11) NOT NULL,
        `premium_days` INT(11) NOT NULL DEFAULT 0,
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

function paymentCenterColumnExists($table, $column)
{
    global $db;

    return (bool)$db->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $db->quote($column))->fetch();
}

function paymentCenterEnsureStripeColumns()
{
    global $db;

    if (!paymentCenterColumnExists('myaac_stripe_transactions', 'product_type')) {
        $db->query("ALTER TABLE `myaac_stripe_transactions` ADD COLUMN `product_type` VARCHAR(20) NOT NULL DEFAULT 'coins' AFTER `account_id`");
    }

    if (!paymentCenterColumnExists('myaac_stripe_transactions', 'premium_days')) {
        $db->query("ALTER TABLE `myaac_stripe_transactions` ADD COLUMN `premium_days` INT(11) NOT NULL DEFAULT 0 AFTER `coins`");
    }
}

function paymentCenterFindPackage($packages, $key)
{
    if (!isset($packages[$key])) {
        return null;
    }

    $package = $packages[$key];
    $package['key'] = $key;
    return $package;
}

function paymentCenterNormalizeCoinPackage($key, $package)
{
    $coins = (int)($package['coins'] ?? 0);
    $bonus = (int)($package['bonus'] ?? ($package['extra'] ?? 0));
    $price = (float)($package['price'] ?? ($package['value'] ?? 0));

    return [
        'key' => $key,
        'type' => 'coins',
        'label' => $package['label'] ?? (($coins + $bonus) . ' Coins'),
        'reward' => $coins . ' Coins' . ($bonus > 0 ? ' +' . $bonus . ' bonus' : ''),
        'coins' => $coins,
        'bonus' => $bonus,
        'price' => $price,
    ];
}

function paymentCenterNormalizePremiumPackage($key, $package)
{
    $days = (int)($package['days'] ?? 0);

    return [
        'key' => $key,
        'type' => 'premium',
        'label' => $package['label'] ?? ($days . ' Days Premium'),
        'reward' => $days . ' premium days',
        'days' => $days,
        'price' => (float)($package['price'] ?? 0),
    ];
}

function paymentCenterStripeEnabled($stripeConfig)
{
    return !empty($stripeConfig['enabled'])
        && !empty($stripeConfig['secret_key'])
        && strpos($stripeConfig['secret_key'], 'PASTE_YOUR') === false;
}

function paymentCenterStripeRequest($endpoint, $params)
{
    global $config;

    $secretKey = $config['stripe']['secret_key'] ?? '';
    $ch = curl_init('https://api.stripe.com/v1/' . ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Stripe connection failed: ' . $error);
    }

    $decoded = json_decode($response, true);
    if ($status < 200 || $status >= 300) {
        $message = $decoded['error']['message'] ?? ('Stripe returned HTTP ' . $status);
        throw new RuntimeException($message);
    }

    return $decoded;
}

$coinsPackages = [];
foreach ($paymentCenter['coins_packages'] as $key => $package) {
    $coinsPackages[$key] = paymentCenterNormalizeCoinPackage($key, $package);
}

$premiumPackages = [];
foreach ($paymentCenter['premium_packages'] as $key => $package) {
    $premiumPackages[$key] = paymentCenterNormalizePremiumPackage($key, $package);
}

if ($action === 'final') {
    echo $twig->render('donate-final.html.twig');
    return;
}

if (!$logged || !$account_logged || !$account_logged->isLoaded()) {
    echo 'To buy coins or premium time you need to be logged in. ' .
        generateLink('?subtopic=accountmanagement&redirect=' . urlencode(BASE_URL . '?subtopic=donate'), 'Login') .
        ' first.';
    return;
}

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['payment_action'] ?? '') === 'manual_request') {
    paymentCenterEnsureTable();

    $productType = ($_POST['product_type'] ?? '') === 'premium' ? 'premium' : 'coins';
    $packageKey = trim((string)($_POST['package_key'] ?? ''));
    $method = ($_POST['payment_method'] ?? '') === 'bank_transfer' ? 'bank_transfer' : 'crypto';
    $network = trim((string)($_POST['payment_network'] ?? ''));
    $transactionId = trim((string)($_POST['transaction_id'] ?? ''));
    $payerNote = trim((string)($_POST['payer_note'] ?? ''));
    $packages = $productType === 'premium' ? $premiumPackages : $coinsPackages;
    $package = paymentCenterFindPackage($packages, $packageKey);

    if ($package === null) {
        $message = ['type' => 'error', 'text' => 'Please select a valid package.'];
    } elseif ($transactionId === '' || strlen($transactionId) < 6) {
        $message = ['type' => 'error', 'text' => 'Please enter the transaction hash, payment ID, or bank receipt reference.'];
    } else {
        $reference = 'NMR-' . (int)$account_logged->getId() . '-' . time() . '-' . random_int(1000, 9999);
        $db->query(
            'INSERT INTO `myaac_payment_requests` ' .
            '(`reference`, `account_id`, `product_type`, `package_key`, `package_label`, `amount`, `currency`, `payment_method`, `payment_network`, `transaction_id`, `payer_note`, `status`, `created_at`) VALUES (' .
            $db->quote($reference) . ', ' .
            (int)$account_logged->getId() . ', ' .
            $db->quote($productType) . ', ' .
            $db->quote($package['key']) . ', ' .
            $db->quote($package['label']) . ', ' .
            (float)$package['price'] . ', ' .
            $db->quote($paymentCenter['currency']) . ', ' .
            $db->quote($method) . ', ' .
            $db->quote($network) . ', ' .
            $db->quote($transactionId) . ', ' .
            $db->quote($payerNote) . ', ' .
            $db->quote('manual_review') . ', NOW())'
        );
        $message = ['type' => 'success', 'text' => 'Donation com sucesso! Reference: ' . $reference . '. Your order will be reviewed by staff.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['payment_action'] ?? '') === 'stripe_checkout') {
    paymentCenterEnsureTable();

    $productType = ($_POST['product_type'] ?? '') === 'premium' ? 'premium' : 'coins';
    $packageKey = trim((string)($_POST['package_key'] ?? ''));
    $packages = $productType === 'premium' ? $premiumPackages : $coinsPackages;
    $package = paymentCenterFindPackage($packages, $packageKey);
    $stripeConfig = $config['stripe'] ?? [];

    if ($package === null) {
        $message = ['type' => 'error', 'text' => 'Please select a valid package.'];
    } elseif (!paymentCenterStripeEnabled($stripeConfig)) {
        $message = ['type' => 'error', 'text' => 'Stripe test keys are not configured yet.'];
    } elseif ((float)$package['price'] < 1) {
        $message = ['type' => 'error', 'text' => 'The minimum Stripe payment is USD 1.00.'];
    } else {
        try {
            $coinsAmount = $productType === 'coins' ? (int)($package['coins'] + ($package['bonus'] ?? 0)) : 0;
            $premiumDays = $productType === 'premium' ? (int)($package['days'] ?? 0) : 0;
            $amountCents = (int)round(((float)$package['price']) * 100);
            $coinField = $stripeConfig['coin_field'] ?? 'coins_transferable';
            if (!in_array($coinField, ['coins', 'coins_transferable'], true)) {
                $coinField = 'coins_transferable';
            }

            $session = paymentCenterStripeRequest('checkout/sessions', [
                'mode' => 'payment',
                'payment_method_types[0]' => 'card',
                'wallet_options[link][display]' => 'never',
                'success_url' => BASE_URL . '?subtopic=donate&action=final&provider=stripe',
                'cancel_url' => BASE_URL . '?subtopic=donate&type=' . $productType . '&payment=cancelled',
                'client_reference_id' => (string)$account_logged->getId(),
                'line_items[0][price_data][currency]' => strtolower($stripeConfig['currency'] ?? $paymentCenter['currency']),
                'line_items[0][price_data][unit_amount]' => $amountCents,
                'line_items[0][price_data][product_data][name]' => $package['label'],
                'line_items[0][quantity]' => 1,
                'metadata[account_id]' => (string)$account_logged->getId(),
                'metadata[product_type]' => $productType,
                'metadata[package_key]' => $package['key'],
                'metadata[coins]' => (string)$coinsAmount,
                'metadata[premium_days]' => (string)$premiumDays,
                'metadata[coin_field]' => $coinField,
            ]);

            $db->query(
                'INSERT INTO `myaac_stripe_transactions` ' .
                '(`checkout_session_id`, `payment_intent_id`, `account_id`, `product_type`, `package_key`, `coins`, `premium_days`, `amount_total`, `currency`, `coin_field`, `status`, `created_at`) VALUES (' .
                $db->quote($session['id']) . ', NULL, ' .
                (int)$account_logged->getId() . ', ' .
                $db->quote($productType) . ', ' .
                $db->quote($package['key']) . ', ' .
                $coinsAmount . ', ' .
                $premiumDays . ', ' .
                $amountCents . ', ' .
                $db->quote(strtolower($stripeConfig['currency'] ?? $paymentCenter['currency'])) . ', ' .
                $db->quote($coinField) . ', ' .
                $db->quote('created') . ', NOW())'
            );

            header('Location: ' . $session['url']);
            exit;
        } catch (Exception $e) {
            $message = ['type' => 'error', 'text' => 'Stripe checkout could not be created: ' . $e->getMessage()];
        }
    }
}

$selectedType = ($_GET['type'] ?? $_POST['product_type'] ?? 'coins') === 'premium' ? 'premium' : 'coins';

echo $twig->render('donate.html.twig', [
    'payment' => $paymentCenter,
    'coins_packages' => $coinsPackages,
    'premium_packages' => $premiumPackages,
    'selected_type' => $selectedType,
    'message' => $message,
    'account_name' => $account_logged->getName(),
]);
