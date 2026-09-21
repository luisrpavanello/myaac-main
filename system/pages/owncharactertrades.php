<?php
defined('MYAAC') or die('Direct access not allowed!');

require_once LIBS . 'ZealotMarket.php';

$title = 'Zealot Market — My Listings';
$market = new ZealotMarket($db, $config);
$marketNavActive = 'listings';
$sellerId = $logged ? (int) $account_logged->getId() : 0;
$listings = [];
$marketError = null;

if (!$logged) {
    $marketError = 'Log in to see and manage your character listings.';
} elseif (!$market->isInstalled()) {
    $marketError = 'Zealot Market has not been installed yet.';
} else {
    $market->settleExpiredListings();
    $listings = $db->query(
        'SELECT a.*, p.`name`, p.`level`, p.`vocation`, UNIX_TIMESTAMP(a.`date_end`) AS `date_end_unix` '
        . 'FROM `myaac_charbazaar` AS a INNER JOIN `players` AS p ON p.`id` = a.`player_id` '
        . 'WHERE a.`account_old` = ' . $sellerId . ' ORDER BY a.`date_start` DESC, a.`id` DESC'
    )->fetchAll();
}
?>
<section class="zealot-market-activity" aria-labelledby="zealot-market-my-listings-title">
    <header class="zealot-market-activity__header">
        <div>
            <h1 id="zealot-market-my-listings-title">My listings</h1>
            <p>Track active sales, completed transfers and characters returned from Market escrow.</p>
        </div>
        <a class="zealot-market-activity__action" href="<?= htmlspecialchars(getLink('createcharacterauction'), ENT_QUOTES, 'UTF-8'); ?>">Sell a character</a>
    </header>
    <?php require SYSTEM . 'templates/zealot_market_nav.php'; ?>

    <?php if ($marketError) { ?>
        <div class="zealot-market-empty"><strong><?= htmlspecialchars($marketError, ENT_QUOTES, 'UTF-8'); ?></strong><?php if (!$logged) { ?><a href="<?= htmlspecialchars(getLink('account/manage'), ENT_QUOTES, 'UTF-8'); ?>">Log in</a><?php } ?></div>
    <?php } elseif (!$listings) { ?>
        <div class="zealot-market-empty"><strong>You have no character listings yet.</strong><span>Choose one of your eligible characters and safely list it in Zealot Market.</span><a href="<?= htmlspecialchars(getLink('createcharacterauction'), ENT_QUOTES, 'UTF-8'); ?>">Sell a character</a></div>
    <?php } else { ?>
        <table class="zealot-market-activity__table">
            <thead><tr><th>Character</th><th>Status</th><th>Current bid</th><th>Ends / completed</th><th class="is-action">Action</th></tr></thead>
            <tbody>
            <?php foreach ($listings as $listing) {
                $open = (int) $listing['status'] === ZealotMarket::STATUS_ACTIVE && (int) $listing['date_end_unix'] > time();
                $status = $open ? 'Live listing' : ZealotMarket::statusLabel((int) $listing['status']);
                $statusClass = $open ? 'live' : ((int) $listing['status'] === ZealotMarket::STATUS_SOLD ? 'sold' : 'closed');
                $amount = max((int) $listing['starting_price'], (int) $listing['bid_price'], (int) $listing['final_price']);
                $dateSource = $open ? $listing['date_end'] : ($listing['completed_at'] ?: ($listing['cancelled_at'] ?: $listing['date_end']));
                $detailUrl = getLinkWithQuery('currentcharactertrades', ['details' => (int) $listing['id']]);
                ?>
                <tr>
                    <td data-label="Character"><strong><?= htmlspecialchars($listing['name'], ENT_QUOTES, 'UTF-8'); ?></strong><br><small>Level <?= number_format((int) $listing['level']); ?> <?= htmlspecialchars($config['vocations'][$listing['vocation']] ?? 'Adventurer', ENT_QUOTES, 'UTF-8'); ?></small></td>
                    <td data-label="Status"><span class="zealot-market-activity__status zealot-market-activity__status--<?= $statusClass; ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-label="Current bid"><?= number_format($amount); ?> <img src="<?= $template_path; ?>/images/account/icon-tibiacointrusted.png" alt="Z"></td>
                    <td data-label="Ends / completed"><?= htmlspecialchars(date('M j, Y' . ($open ? ' H:i' : ''), strtotime((string) $dateSource)), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="is-action" data-label="Action"><a class="zealot-market-activity__action" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8'); ?>">Access</a></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>
