<?php

/**
 * Read-only operational verification for Zealot Market.
 *
 * Run after deployments and before enabling the marketplace publicly:
 *   php system/bin/verify_zealot_market.php
 *
 * It never creates listings, moves characters or changes coin balances.  The
 * checks cover the same escrow, winning-bid and settlement invariants used by
 * the create-listing, bid and automatic-sale flows.
 */
if (PHP_SAPI !== 'cli') {
    exit("This script can be run only from the command line.\n");
}

require_once __DIR__ . '/../../common.php';
require_once SYSTEM . 'functions.php';
require_once SYSTEM . 'init.php';
require_once LIBS . 'ZealotMarket.php';

$market = new ZealotMarket($db, $config);
$errors = [];
$warnings = [];
$checks = 0;

$check = static function (bool $condition, string $message) use (&$errors, &$checks): void {
    $checks++;
    if (!$condition) {
        $errors[] = $message;
    }
};

$count = static function (string $sql) use ($db): int {
    $row = $db->query($sql)->fetch();

    return (int) ($row['total'] ?? 0);
};

$check($market->isInstalled(), 'Market schema is incomplete. Run: php system/bin/install_zealot_market.php');
if (!$market->isInstalled()) {
    fwrite(STDERR, "Zealot Market verification failed.\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  ERROR: {$error}\n");
    }
    exit(1);
}

// A deterministic price-rule check catches accidental configuration mistakes
// without modifying an existing listing.
$increment = max(1, (int) ($config['bazaar_bid'] ?? 1));
$check(
    $market->minimumBidFor(['starting_price' => 100, 'bid_price' => 0, 'price' => 100]) === 100 + $increment,
    'The opening bid calculation is not using the configured Market increment.'
);
$check(
    $market->minimumBidFor(['starting_price' => 100, 'bid_price' => 225, 'price' => 225]) === 225 + $increment,
    'The next-bid calculation is not using the current winning bid.'
);

$negativeBalances = $count('SELECT COUNT(*) AS `total` FROM `accounts` WHERE `coins_transferable` < 0');
$check($negativeBalances === 0, "{$negativeBalances} account(s) have a negative transferable-coin balance.");

$brokenActiveEscrow = $count(
    'SELECT COUNT(*) AS `total` FROM `myaac_charbazaar` AS a '
    . 'LEFT JOIN `players` AS p ON p.`id` = a.`player_id` '
    . 'LEFT JOIN `accounts` AS seller ON seller.`id` = a.`account_old` '
    . 'LEFT JOIN `accounts` AS escrow ON escrow.`id` = a.`escrow_account_id` '
    . 'WHERE a.`status` = ' . ZealotMarket::STATUS_ACTIVE . ' AND ('
    . 'p.`id` IS NULL OR seller.`id` IS NULL OR escrow.`id` IS NULL OR a.`escrow_account_id` = 0 '
    . 'OR p.`account_id` <> a.`escrow_account_id`)'
);
$check($brokenActiveEscrow === 0, "{$brokenActiveEscrow} active listing(s) are not correctly protected in Market escrow.");

$expiredLiveListings = $count(
    'SELECT COUNT(*) AS `total` FROM `myaac_charbazaar` WHERE `status` = ' . ZealotMarket::STATUS_ACTIVE . ' AND `date_end` <= NOW()'
);
if ($expiredLiveListings > 0) {
    $warnings[] = "{$expiredLiveListings} expired live listing(s) are waiting for the next settlement request.";
}

$brokenWinningBids = $count(
    'SELECT COUNT(*) AS `total` FROM `myaac_charbazaar` AS a '
    . 'LEFT JOIN (SELECT `auction_id`, COUNT(*) AS `winning_count`, MAX(`bid`) AS `winning_bid`, MAX(`account_id`) AS `winning_account` '
    . 'FROM `myaac_charbazaar_bid` WHERE `is_winning` = 1 GROUP BY `auction_id`) AS winning ON winning.`auction_id` = a.`id` '
    . 'WHERE a.`status` = ' . ZealotMarket::STATUS_ACTIVE . ' AND ('
    . '(a.`bid_account` = 0 AND a.`bid_price` <> 0) OR '
    . '(a.`bid_account` > 0 AND (a.`bid_price` <= 0 OR winning.`winning_count` <> 1 OR winning.`winning_bid` <> a.`bid_price` OR winning.`winning_account` <> a.`bid_account`))'
    . ')'
);
$check($brokenWinningBids === 0, "{$brokenWinningBids} active listing(s) have an inconsistent current winning bid.");

$brokenSales = $count(
    'SELECT COUNT(*) AS `total` FROM `myaac_charbazaar` AS a '
    . 'LEFT JOIN `players` AS p ON p.`id` = a.`player_id` '
    . 'WHERE a.`status` = ' . ZealotMarket::STATUS_SOLD . ' AND ('
    . 'p.`id` IS NULL OR a.`account_new` = 0 OR p.`account_id` <> a.`account_new` '
    . 'OR a.`bid_account` <> a.`account_new` OR a.`final_price` <= 0 OR a.`completed_at` IS NULL'
    . ')'
);
$check($brokenSales === 0, "{$brokenSales} completed sale(s) have an invalid buyer, character owner or final price.");

$brokenReturns = $count(
    'SELECT COUNT(*) AS `total` FROM `myaac_charbazaar` AS a '
    . 'LEFT JOIN `players` AS p ON p.`id` = a.`player_id` '
    . 'WHERE a.`status` IN (' . ZealotMarket::STATUS_CANCELLED . ', ' . ZealotMarket::STATUS_EXPIRED . ') '
    . 'AND (p.`id` IS NULL OR p.`account_id` <> a.`account_old`)'
);
$check($brokenReturns === 0, "{$brokenReturns} cancelled or expired listing(s) did not return their character to the seller.");

$brokenPayouts = $count(
    'SELECT COUNT(*) AS `total` FROM `myaac_charbazaar` AS a '
    . 'LEFT JOIN (SELECT `auction_id`, `account_id`, SUM(`amount`) AS `amount` FROM `myaac_zealot_market_ledger` '
    . "WHERE `entry_type` = 'sale_payout' GROUP BY `auction_id`, `account_id`) AS payout "
    . 'ON payout.`auction_id` = a.`id` AND payout.`account_id` = a.`account_old` '
    . 'WHERE a.`status` = ' . ZealotMarket::STATUS_SOLD . ' AND ('
    . 'payout.`auction_id` IS NULL OR payout.`amount` <> (a.`final_price` - FLOOR(a.`final_price` * a.`tax_rate` / 100))'
    . ')'
);
$check($brokenPayouts === 0, "{$brokenPayouts} completed sale(s) have an inconsistent seller payout ledger entry.");

$invalidHiddenHistory = $count(
    'SELECT COUNT(*) AS `total` FROM `myaac_zealot_market_hidden_history` AS h '
    . 'LEFT JOIN `accounts` AS account ON account.`id` = h.`account_id` '
    . 'LEFT JOIN `myaac_charbazaar` AS a ON a.`id` = h.`auction_id` '
    . 'WHERE account.`id` IS NULL OR a.`id` IS NULL OR a.`status` = ' . ZealotMarket::STATUS_ACTIVE
);
$check($invalidHiddenHistory === 0, "{$invalidHiddenHistory} hidden-history record(s) point to an invalid or live listing.");

$orphanLedger = $count(
    'SELECT COUNT(*) AS `total` FROM `myaac_zealot_market_ledger` AS l '
    . 'LEFT JOIN `accounts` AS account ON account.`id` = l.`account_id` '
    . 'LEFT JOIN `myaac_charbazaar` AS a ON a.`id` = l.`auction_id` '
    . 'WHERE account.`id` IS NULL OR (l.`auction_id` IS NOT NULL AND a.`id` IS NULL)'
);
$check($orphanLedger === 0, "{$orphanLedger} Market ledger entry/entries reference a missing account or listing.");

echo "Zealot Market verification: {$checks} checks completed.\n";
foreach ($warnings as $warning) {
    echo "WARNING: {$warning}\n";
}

if ($errors) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: {$error}\n");
    }
    exit(1);
}

echo "PASS: Escrow, bids, settlement records, personal history and balances are consistent.\n";
