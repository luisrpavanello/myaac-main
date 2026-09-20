<?php
/**
 * Zealot Market landing page.
 *
 * This page is the branded entry point for the transactional marketplace.
 */
defined('MYAAC') or die('Direct access not allowed!');

require_once LIBS . 'ZealotMarket.php';

$title = 'Zealot Market';
$activeListings = 0;

if ($db->hasTable('myaac_charbazaar')) {
    $activeListingsQuery = $db->query(
        'SELECT COUNT(*) AS `total` FROM `myaac_charbazaar` WHERE `status` = ' . ZealotMarket::STATUS_ACTIVE . ' AND `date_end` > NOW()'
    );
    $activeListings = (int) ($activeListingsQuery->fetch()['total'] ?? 0);
}

$exchangeLinks = [
    'browse' => getLink('currentcharactertrades'),
    'sell' => getLink('createcharacterauction'),
    'bids' => getLink('ownbids'),
    'listings' => getLink('owncharactertrades'),
    'history' => getLink('pastcharactertrades'),
    'account' => getLink('account/manage'),
];
?>

<section class="zealot-exchange" aria-labelledby="zealot-exchange-title">
    <header class="zealot-exchange__hero">
        <span class="zealot-exchange__eyebrow">Official character marketplace</span>
        <h1 id="zealot-exchange-title">Zealot Market</h1>
        <p>Buy and sell Zealot characters through a single, protected marketplace.</p>
        <div class="zealot-exchange__hero-actions">
            <a class="zealot-exchange__button" href="<?= $exchangeLinks['browse']; ?>">Browse characters</a>
            <a class="zealot-exchange__button zealot-exchange__button--quiet" href="<?= $logged ? $exchangeLinks['sell'] : $exchangeLinks['account']; ?>">
                <?= $logged ? 'Sell a character' : 'Log in to sell'; ?>
            </a>
        </div>
    </header>

    <div class="zealot-exchange__summary" aria-label="Marketplace status">
        <strong><?= number_format($activeListings); ?></strong>
        <span>active <?= $activeListings === 1 ? 'listing' : 'listings'; ?></span>
        <span class="zealot-exchange__summary-rule">Transfers are completed only through Zealot Market.</span>
    </div>

    <div class="zealot-exchange__grid">
        <article class="zealot-exchange__card">
            <span class="zealot-exchange__card-kicker">Buy</span>
            <h2>Find your next character</h2>
            <p>Compare active listings and place a bid on the character that fits your adventure.</p>
            <a href="<?= $exchangeLinks['browse']; ?>">View listings <span aria-hidden="true">→</span></a>
        </article>

        <article class="zealot-exchange__card">
            <span class="zealot-exchange__card-kicker">Sell</span>
            <h2>Create a listing</h2>
            <p>Choose one of your eligible characters, define the starting bid and publish it securely.</p>
            <a href="<?= $logged ? $exchangeLinks['sell'] : $exchangeLinks['account']; ?>">
                <?= $logged ? 'Start selling' : 'Log in to sell'; ?> <span aria-hidden="true">→</span>
            </a>
        </article>

        <article class="zealot-exchange__card">
            <span class="zealot-exchange__card-kicker">Activity</span>
            <h2>Keep track of everything</h2>
            <p>Review your bids, active listings and completed market sales whenever you need.</p>
            <div class="zealot-exchange__card-links">
                <a href="<?= $exchangeLinks['bids']; ?>">My bids</a>
                <a href="<?= $exchangeLinks['listings']; ?>">My listings</a>
                <a href="<?= $exchangeLinks['history']; ?>">History</a>
            </div>
        </article>
    </div>

    <p class="zealot-exchange__notice">
        <strong>How it works:</strong> characters stay in the Zealot transfer flow throughout the Market; never arrange a sale outside this marketplace.
    </p>
</section>
