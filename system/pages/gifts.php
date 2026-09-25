<?php
global $config, $db, $logged, $account_logged, $title, $action;

defined('MYAAC') or die('Direct access not allowed!');

function shopTableExists($table)
{
    global $db;

    $query = $db->query('SHOW TABLES LIKE ' . $db->quote($table));
    return (bool)$query->fetch();
}

$isHistory = $action === 'show_history' || $action === 'history';
$title = $isHistory ? 'Shop History' : 'Shop Offer';

// The legacy Shop Offer was coupled to PagSeguro, which only settles in BRL.
// The live storefront uses the USD Payment Center instead, so never expose a
// BRL-priced offer that could disagree with the checkout currency.
if (!$isHistory) {
    header('Location: ' . BASE_URL . '?subtopic=donate&type=coins', true, 302);
    exit;
}

require_once PLUGINS . 'pagseguro/config.php';

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
