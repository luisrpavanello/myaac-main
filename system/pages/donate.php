<?php
global $config, $twig, $logged, $account_logged, $db, $title, $action;

defined('MYAAC') or die('Direct access not allowed!');

$title = 'Payment Center';

require_once PLUGINS . 'pagseguro/config.php';

$defaults = [
    'currency' => 'USD',
    'support_contact' => 'support@numenor.global',
    'stripe_checkout_url' => '',
    'paypal_checkout_url' => '',
    'bank_transfer_enabled' => false,
    'bank_transfer_instructions' => 'Contact support before sending a bank transfer.',
    'coins_packages' => $config['pagSeguro']['donates'] ?? [],
    'premium_packages' => [
        'premium_30' => ['label' => '30 Days Premium Account', 'days' => 30, 'price' => 9.99],
        'premium_90' => ['label' => '90 Days Premium Account', 'days' => 90, 'price' => 24.99],
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
        'price' => $price,
    ];
}

function paymentCenterNormalizePremiumPackage($key, $package)
{
    $days = (int)($package['days'] ?? 0);

    return [
        'key' => $key,
        'type' => 'premium',
        'label' => $package['label'] ?? ($days . ' Days Premium Account'),
        'reward' => $days . ' premium days',
        'price' => (float)($package['price'] ?? 0),
    ];
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
        $message = ['type' => 'success', 'text' => 'Payment confirmation received. Reference: ' . $reference . '. Your order will be reviewed by staff.'];
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
