<?php

/**
 * End-to-end Market lifecycle test for a dedicated test database.
 *
 * This deliberately creates a listing, holds a bid, expires it and completes
 * a sale. It is protected against accidental use with normal player accounts.
 *
 * Example (only in a disposable test database):
 * ZEALOT_MARKET_E2E=1 php system/bin/test_zealot_market_e2e.php \
 *   --seller=zealot_e2e_seller --buyer=zealot_e2e_buyer --player=123
 */
if (PHP_SAPI !== 'cli') {
    exit("This script can be run only from the command line.\n");
}

if (getenv('ZEALOT_MARKET_E2E') !== '1') {
    exit("Refusing to mutate data. Set ZEALOT_MARKET_E2E=1 only for a disposable test database.\n");
}

require_once __DIR__ . '/../../common.php';
require_once SYSTEM . 'functions.php';
require_once SYSTEM . 'init.php';
require_once LIBS . 'ZealotMarket.php';

$options = getopt('', ['seller:', 'buyer:', 'player:']);
$sellerName = (string) ($options['seller'] ?? '');
$buyerName = (string) ($options['buyer'] ?? '');
$playerId = filter_var($options['player'] ?? null, FILTER_VALIDATE_INT);

if (!str_starts_with($sellerName, 'zealot_e2e_') || !str_starts_with($buyerName, 'zealot_e2e_') || !$playerId) {
    exit("Use dedicated accounts beginning with zealot_e2e_ and provide --player=<id>.\n");
}

$market = new ZealotMarket($db, $config);
if (!$market->isInstalled()) {
    exit("Market schema is incomplete. Run the installer first.\n");
}

$seller = $db->query('SELECT `id`, `coins_transferable` FROM `accounts` WHERE `name` = ' . $db->quote($sellerName))->fetch();
$buyer = $db->query('SELECT `id`, `coins_transferable` FROM `accounts` WHERE `name` = ' . $db->quote($buyerName))->fetch();
$player = $db->query('SELECT `id`, `name`, `account_id` FROM `players` WHERE `id` = ' . (int) $playerId)->fetch();

if (!$seller || !$buyer || !$player) {
    exit("The supplied seller, buyer or player fixture does not exist.\n");
}
if ((int) $seller['id'] === (int) $buyer['id'] || (int) $player['account_id'] !== (int) $seller['id']) {
    exit("The fixture player must belong to the dedicated seller, and buyer and seller must differ.\n");
}
if ($db->query('SELECT `id` FROM `myaac_charbazaar` WHERE `player_id` = ' . (int) $playerId . ' AND `status` = 0')->fetch()) {
    exit("The fixture player already has an active listing. Use a fresh test character.\n");
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('E2E assertion failed: ' . $message);
    }
};

try {
    $startingPrice = 100;
    $listingFee = max(0, (int) ($config['bazaar_create'] ?? 0));
    $increment = max(1, (int) ($config['bazaar_bid'] ?? 1));
    $bid = $startingPrice + $increment;
    $taxRate = max(0, min(100, (int) ($config['bazaar_tax'] ?? 0)));
    $sellerStart = (int) $seller['coins_transferable'];
    $buyerStart = (int) $buyer['coins_transferable'];

    $assert($sellerStart >= $listingFee, 'seller does not have enough coins for the listing fee');
    $assert($buyerStart >= $bid, 'buyer does not have enough coins for the opening bid');

    $listingId = $market->createListing((int) $seller['id'], (int) $player['id'], $startingPrice, 1);
    $listing = $db->query('SELECT * FROM `myaac_charbazaar` WHERE `id` = ' . $listingId)->fetch();
    $ownerAfterListing = $db->query('SELECT `account_id` FROM `players` WHERE `id` = ' . (int) $player['id'])->fetch();
    $assert((int) $listing['status'] === ZealotMarket::STATUS_ACTIVE, 'listing was not created as active');
    $assert((int) $ownerAfterListing['account_id'] === (int) $listing['escrow_account_id'], 'character was not moved to escrow');

    $market->placeBid((int) $buyer['id'], $listingId, $bid);
    $listing = $db->query('SELECT * FROM `myaac_charbazaar` WHERE `id` = ' . $listingId)->fetch();
    $buyerAfterBid = $db->query('SELECT `coins_transferable` FROM `accounts` WHERE `id` = ' . (int) $buyer['id'])->fetch();
    $assert((int) $listing['bid_account'] === (int) $buyer['id'] && (int) $listing['bid_price'] === $bid, 'winning bid was not stored');
    $assert((int) $buyerAfterBid['coins_transferable'] === $buyerStart - $bid, 'bid escrow did not debit the buyer exactly once');

    // The normal request lifecycle settles expired listings. Expire this
    // disposable fixture explicitly so the final ownership transfer is tested.
    $db->exec('UPDATE `myaac_charbazaar` SET `date_end` = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE `id` = ' . $listingId);
    $market->settleExpiredListings();

    $listing = $db->query('SELECT * FROM `myaac_charbazaar` WHERE `id` = ' . $listingId)->fetch();
    $ownerAfterSale = $db->query('SELECT `account_id` FROM `players` WHERE `id` = ' . (int) $player['id'])->fetch();
    $sellerAfterSale = $db->query('SELECT `coins_transferable` FROM `accounts` WHERE `id` = ' . (int) $seller['id'])->fetch();
    $payout = $bid - (int) floor($bid * $taxRate / 100);
    $assert((int) $listing['status'] === ZealotMarket::STATUS_SOLD, 'expired listing was not settled as sold');
    $assert((int) $listing['account_new'] === (int) $buyer['id'], 'buyer was not recorded as the new owner');
    $assert((int) $ownerAfterSale['account_id'] === (int) $buyer['id'], 'character was not transferred to the buyer');
    $assert((int) $listing['final_price'] === $bid, 'final sale price is incorrect');
    $assert((int) $sellerAfterSale['coins_transferable'] === $sellerStart - $listingFee + $payout, 'seller payout is incorrect');

    echo "PASS: listing #{$listingId} completed: escrow, bid hold, sale payout and character transfer verified.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
