<?php
global $config, $db, $logged, $account_logged, $title, $action;

defined('MYAAC') or die('Direct access not allowed!');

require_once PLUGINS . 'pagseguro/config.php';

function shopTableExists($table)
{
    global $db;

    $query = $db->query('SHOW TABLES LIKE ' . $db->quote($table));
    return (bool)$query->fetch();
}

function shopMoney($value)
{
    return 'R$' . number_format((float)$value, 2, ',', '.');
}

$isHistory = $action === 'show_history' || $action === 'history';
$title = $isHistory ? 'Shop History' : 'Shop Offer';

if ($isHistory) {
    if (!$logged || !$account_logged || !$account_logged->isLoaded()) {
        echo '<p>To view your shop history you need to be logged in. ' .
            generateLink(getLink('account/manage'), 'Login') . ' first.</p>';
        return;
    }

    $accountId = (int)$account_logged->getId();
    $transactions = [];
    $items = [];

    if (shopTableExists('pagseguro_transactions')) {
        $query = $db->query(
            'SELECT `transaction_code`, `payment_method`, `payment_status`, `coins_amount`, `delivered`, `created_at`, `updated_at` ' .
            'FROM `pagseguro_transactions` WHERE `account_id` = ' . $accountId . ' ORDER BY `created_at` DESC LIMIT 30'
        );
        $transactions = $query->fetchAll();
    }

    if (shopTableExists('myaac_send_items')) {
        $query = $db->query(
            'SELECT `transaction_code`, `item_name`, `item_count`, `payment_method`, `payment_status`, `status`, `created_at`, `updated_at` ' .
            'FROM `myaac_send_items` WHERE `account_id` = ' . $accountId . ' ORDER BY `created_at` DESC LIMIT 30'
        );
        $items = $query->fetchAll();
    }

    echo '<table width="100%" border="0" cellpadding="4" cellspacing="1">';
    echo '<tr bgcolor="' . $config['vdarkborder'] . '" class="white"><td colspan="6"><b>Point Purchases</b></td></tr>';
    echo '<tr bgcolor="' . $config['darkborder'] . '"><td><b>Date</b></td><td><b>Transaction</b></td><td><b>Coins</b></td><td><b>Status</b></td><td><b>Delivered</b></td><td><b>Method</b></td></tr>';
    if (count($transactions) === 0) {
        echo '<tr bgcolor="' . $config['lightborder'] . '"><td colspan="6">No point purchases were found for this account.</td></tr>';
    } else {
        $i = 0;
        foreach ($transactions as $transaction) {
            $bg = getStyle($i++);
            echo '<tr bgcolor="' . $bg . '">';
            echo '<td>' . htmlspecialchars($transaction['created_at']) . '</td>';
            echo '<td>' . htmlspecialchars($transaction['transaction_code']) . '</td>';
            echo '<td>' . (int)$transaction['coins_amount'] . '</td>';
            echo '<td>' . htmlspecialchars($transaction['payment_status']) . '</td>';
            echo '<td>' . ($transaction['delivered'] === '1' ? 'Yes' : 'No') . '</td>';
            echo '<td>' . htmlspecialchars($transaction['payment_method']) . '</td>';
            echo '</tr>';
        }
    }
    echo '</table><br />';

    echo '<table width="100%" border="0" cellpadding="4" cellspacing="1">';
    echo '<tr bgcolor="' . $config['vdarkborder'] . '" class="white"><td colspan="7"><b>Item Purchases</b></td></tr>';
    echo '<tr bgcolor="' . $config['darkborder'] . '"><td><b>Date</b></td><td><b>Transaction</b></td><td><b>Item</b></td><td><b>Count</b></td><td><b>Status</b></td><td><b>Delivery</b></td><td><b>Method</b></td></tr>';
    if (count($items) === 0) {
        echo '<tr bgcolor="' . $config['lightborder'] . '"><td colspan="7">No item purchases were found for this account.</td></tr>';
    } else {
        $statusMap = ['0' => 'Pending', '1' => 'Approved', '2' => 'Delivered', '3' => 'Canceled'];
        $i = 0;
        foreach ($items as $item) {
            $bg = getStyle($i++);
            echo '<tr bgcolor="' . $bg . '">';
            echo '<td>' . htmlspecialchars($item['created_at']) . '</td>';
            echo '<td>' . htmlspecialchars($item['transaction_code']) . '</td>';
            echo '<td>' . htmlspecialchars($item['item_name']) . '</td>';
            echo '<td>' . (int)$item['item_count'] . '</td>';
            echo '<td>' . htmlspecialchars($item['payment_status']) . '</td>';
            echo '<td>' . ($statusMap[$item['status']] ?? htmlspecialchars($item['status'])) . '</td>';
            echo '<td>' . htmlspecialchars($item['payment_method']) . '</td>';
            echo '</tr>';
        }
    }
    echo '</table>';
    return;
}

$donates = $config['pagSeguro']['donates'] ?? [];
$boxes = $config['pagSeguro']['boxes'] ?? [];
$validBoxes = array_filter($boxes, static function ($box) {
    return isset($box['id'], $box['name']) && $box['id'] !== 'xxxxx';
});

echo '<table width="100%" border="0" cellpadding="4" cellspacing="1">';
echo '<tr bgcolor="' . $config['vdarkborder'] . '" class="white"><td colspan="4"><b>Available Coin Packages</b></td></tr>';
echo '<tr bgcolor="' . $config['darkborder'] . '"><td><b>Package</b></td><td><b>Reward</b></td><td><b>Price</b></td><td><b>Action</b></td></tr>';
if (count($donates) === 0) {
    echo '<tr bgcolor="' . $config['lightborder'] . '"><td colspan="4">No coin packages configured.</td></tr>';
} else {
    $i = 0;
    foreach ($donates as $donate) {
        $coins = (int)$donate['coins'];
        $extra = (int)($donate['extra'] ?? 0);
        $reward = $coins . ' Coins' . ($extra > 0 ? ' +' . $extra . ' bonus' : '');
        echo '<tr bgcolor="' . getStyle($i++) . '">';
        echo '<td>' . htmlspecialchars($donate['id']) . '</td>';
        echo '<td>' . htmlspecialchars($reward) . '</td>';
        echo '<td>' . shopMoney($donate['value']) . '</td>';
        echo '<td>' . generateLink('?subtopic=donate&type=coins', 'Buy Coins') . '</td>';
        echo '</tr>';
    }
}
echo '</table><br />';

echo '<table width="100%" border="0" cellpadding="4" cellspacing="1">';
echo '<tr bgcolor="' . $config['vdarkborder'] . '" class="white"><td colspan="4"><b>Available Item Offers</b></td></tr>';
echo '<tr bgcolor="' . $config['darkborder'] . '"><td><b>Item</b></td><td><b>Description</b></td><td><b>Price</b></td><td><b>Action</b></td></tr>';
if (count($validBoxes) === 0) {
    echo '<tr bgcolor="' . $config['lightborder'] . '"><td colspan="4">No item offers configured yet.</td></tr>';
} else {
    $i = 0;
    foreach ($validBoxes as $box) {
        echo '<tr bgcolor="' . getStyle($i++) . '">';
        echo '<td>' . htmlspecialchars($box['name']) . '</td>';
        echo '<td>' . htmlspecialchars($box['description'] ?? '') . '</td>';
        echo '<td>' . shopMoney($box['value']) . '</td>';
        echo '<td>' . generateLink(getLink('boxes'), 'Buy Box') . '</td>';
        echo '</tr>';
    }
}
echo '</table>';
