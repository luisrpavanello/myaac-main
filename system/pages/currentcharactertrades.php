<?php
defined('MYAAC') or die('Direct access not allowed!');

require_once LIBS . 'ZealotMarket.php';

$title = 'Zealot Market — Listings';
$market = new ZealotMarket($db, $config);
$marketCsrf = ZealotMarket::csrfToken();
$marketMessage = null;
$marketError = null;

if (!$market->isInstalled()) {
    $marketError = 'Zealot Market has not been installed. Run the market installer before opening listings.';
} else {
    $market->settleExpiredListings();

    if (($_GET['action'] ?? '') === 'bid' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (!$logged) {
                throw new RuntimeException('Log in before placing a bid.');
            }
            $market->verifyCsrf($_POST['market_csrf'] ?? null);
            $market->placeBid(
                (int) $account_logged->getId(),
                (int) ($_POST['auction_iden'] ?? 0),
                (int) ($_POST['maxbid'] ?? 0)
            );
            $marketMessage = 'Your bid is now the current winning bid. The amount is safely held in Zealot Market escrow.';
        } catch (Throwable $exception) {
            $marketError = $exception->getMessage();
        }
    }

    if (($_GET['action'] ?? '') === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (!$logged) {
                throw new RuntimeException('Log in before cancelling a listing.');
            }
            $market->verifyCsrf($_POST['market_csrf'] ?? null);
            $market->cancelListing((int) $account_logged->getId(), (int) ($_POST['auction_iden'] ?? 0));
            $marketMessage = 'Your listing was cancelled and the character has been returned to your account.';
        } catch (Throwable $exception) {
            $marketError = $exception->getMessage();
        }
    }
}

$marketVocation = filter_input(INPUT_GET, 'vocation', FILTER_VALIDATE_INT) ?: 0;
$marketMinLevel = filter_input(INPUT_GET, 'min_level', FILTER_VALIDATE_INT) ?: 0;
$marketMaxLevel = filter_input(INPUT_GET, 'max_level', FILTER_VALIDATE_INT) ?: 0;
$marketOrder = $_GET['order'] ?? 'ending';
$marketOrders = [
    'ending' => 'a.`date_end` ASC',
    'newest' => 'a.`date_start` DESC',
    'level_high' => 'p.`level` DESC',
    'level_low' => 'p.`level` ASC',
    'price_low' => 'a.`price` ASC',
    'price_high' => 'a.`price` DESC',
];
if (!isset($marketOrders[$marketOrder])) {
    $marketOrder = 'ending';
}
if ($marketMaxLevel > 0 && $marketMaxLevel < $marketMinLevel) {
    $marketMaxLevel = $marketMinLevel;
}

$subtopic = 'currentcharactertrades';
$listingId = filter_input(INPUT_GET, 'details', FILTER_VALIDATE_INT) ?: 0;

if ($listingId > 0 && $marketError === null && $marketMessage === null) {
    // The legacy detail template expects this variable. Keep the route
    // compatible while the listing and transaction handling live here.
    try {
        $market->refreshActiveListingSnapshot($listingId);
        $getPageDetails = $listingId;
        require SYSTEM . 'pages/char_bazaar/details.php';
        return;
    } catch (Throwable $exception) {
        $marketError = $exception->getMessage();
    }
}
?>

<section class="zealot-market-listings" aria-labelledby="zealot-market-listings-title">
    <header class="zealot-market-listings__header">
        <div>
            <span class="zealot-market-listings__eyebrow">Character marketplace</span>
            <h1 id="zealot-market-listings-title">Characters for sale</h1>
            <p>Explore live listings created by other Zealot players.</p>
        </div>
        <a class="zealot-market-listings__sell" href="<?= $logged ? getLink('createcharacterauction') : getLink('account/manage'); ?>">
            <?= $logged ? 'Sell a character' : 'Log in to sell'; ?>
        </a>
    </header>

    <?php $marketNavActive = 'browse'; require SYSTEM . 'templates/zealot_market_nav.php'; ?>

    <?php if ($marketMessage) { ?>
        <div class="zealot-market-feedback zealot-market-feedback--success"><?= htmlspecialchars($marketMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>
    <?php if ($marketError) { ?>
        <div class="zealot-market-feedback zealot-market-feedback--error"><?= htmlspecialchars($marketError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>

    <form class="zealot-market-filters" method="get" action="<?= getLink('currentcharactertrades'); ?>">
        <label>
            <span>Vocation</span>
            <select name="vocation">
                <option value="0">All vocations</option>
                <?php foreach ($config['vocations'] as $id => $name) {
                    if ((int) $id === 0) {
                        continue;
                    }
                    ?>
                    <option value="<?= (int) $id; ?>"<?= $marketVocation === (int) $id ? ' selected' : ''; ?>><?= htmlspecialchars(siteText($name), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php } ?>
            </select>
        </label>
        <label>
            <span>Minimum level</span>
            <input name="min_level" type="number" min="1" value="<?= $marketMinLevel ?: ''; ?>" placeholder="Any">
        </label>
        <label>
            <span>Maximum level</span>
            <input name="max_level" type="number" min="1" value="<?= $marketMaxLevel ?: ''; ?>" placeholder="Any">
        </label>
        <label>
            <span>Sort by</span>
            <select name="order">
                <option value="ending"<?= $marketOrder === 'ending' ? ' selected' : ''; ?>>Ending soon</option>
                <option value="newest"<?= $marketOrder === 'newest' ? ' selected' : ''; ?>>Newest listing</option>
                <option value="level_high"<?= $marketOrder === 'level_high' ? ' selected' : ''; ?>>Highest level</option>
                <option value="level_low"<?= $marketOrder === 'level_low' ? ' selected' : ''; ?>>Lowest level</option>
                <option value="price_low"<?= $marketOrder === 'price_low' ? ' selected' : ''; ?>>Lowest price</option>
                <option value="price_high"<?= $marketOrder === 'price_high' ? ' selected' : ''; ?>>Highest price</option>
            </select>
        </label>
        <button type="submit">Apply filters</button>
        <?php if ($marketVocation || $marketMinLevel || $marketMaxLevel || $marketOrder !== 'ending') { ?>
            <a class="zealot-market-filters__reset" href="<?= getLink('currentcharactertrades'); ?>">Reset</a>
        <?php } ?>
    </form>

    <?php if ($marketError !== null && !$market->isInstalled()) { ?>
        <div class="zealot-market-empty"><strong>Zealot Market is not ready yet.</strong><span><?= htmlspecialchars($marketError, ENT_QUOTES, 'UTF-8'); ?></span></div>
    <?php } else {
        $marketConditions = ['a.`status` = ' . ZealotMarket::STATUS_ACTIVE, 'a.`date_end` > NOW()'];
        if ($marketVocation > 0 && isset($config['vocations'][$marketVocation])) {
            $marketConditions[] = 'p.`vocation` = ' . (int) $marketVocation;
        }
        if ($marketMinLevel > 0) {
            $marketConditions[] = 'p.`level` >= ' . (int) $marketMinLevel;
        }
        if ($marketMaxLevel > 0) {
            $marketConditions[] = 'p.`level` <= ' . (int) $marketMaxLevel;
        }
        $auctions = $db->query(
            'SELECT a.*, UNIX_TIMESTAMP(a.`date_end`) AS `date_end_unix`, UNIX_TIMESTAMP(a.`date_start`) AS `date_start_unix` FROM `myaac_charbazaar` AS a INNER JOIN `players` AS p ON p.`id` = a.`player_id` '
            . 'WHERE ' . implode(' AND ', $marketConditions) . ' ORDER BY ' . $marketOrders[$marketOrder]
        )->fetchAll();
        foreach ($auctions as $auction) {
            try {
                $market->refreshActiveListingSnapshot((int) $auction['id']);
            } catch (Throwable $exception) {
                error_log('Zealot Market snapshot refresh failed for listing ' . (int) $auction['id'] . ': ' . $exception->getMessage());
            }
        }
        // Re-read after a refresh so the cards always render the server's
        // most recently saved outfit and inventory state.
        $auctions = $db->query(
            'SELECT a.*, UNIX_TIMESTAMP(a.`date_end`) AS `date_end_unix`, UNIX_TIMESTAMP(a.`date_start`) AS `date_start_unix` FROM `myaac_charbazaar` AS a INNER JOIN `players` AS p ON p.`id` = a.`player_id` '
            . 'WHERE ' . implode(' AND ', $marketConditions) . ' ORDER BY ' . $marketOrders[$marketOrder]
        )->fetchAll();
        require SYSTEM . 'pages/char_bazaar/list_auctions.php';
    } ?>
</section>
