<?php
defined('MYAAC') or die('Direct access not allowed!');

require_once LIBS . 'ZealotMarket.php';

$title = 'Zealot Market — My Bids';
$market = new ZealotMarket($db, $config);
$marketNavActive = 'bids';
$buyerId = $logged ? (int) $account_logged->getId() : 0;
$bids = [];
$marketError = null;

if (!$logged) {
    $marketError = 'Log in to see the bids you have placed in Zealot Market.';
} elseif (!$market->isInstalled()) {
    $marketError = 'Zealot Market has not been installed yet.';
} else {
    $market->settleExpiredListings();
    $bids = $db->query(
        'SELECT b.`bid` AS `my_bid`, b.`is_winning`, b.`date` AS `bid_date`, a.*, p.`name`, p.`level`, p.`vocation`, UNIX_TIMESTAMP(a.`date_end`) AS `date_end_unix` '
        . 'FROM `myaac_charbazaar_bid` AS b '
        . 'INNER JOIN (SELECT `auction_id`, MAX(`id`) AS `latest_id` FROM `myaac_charbazaar_bid` WHERE `account_id` = ' . $buyerId . ' GROUP BY `auction_id`) AS latest ON latest.`latest_id` = b.`id` '
        . 'INNER JOIN `myaac_charbazaar` AS a ON a.`id` = b.`auction_id` '
        . 'INNER JOIN `players` AS p ON p.`id` = a.`player_id` '
        . 'ORDER BY b.`date` DESC, b.`id` DESC'
    )->fetchAll();
}
?>
<section class="zealot-market-activity" aria-labelledby="zealot-market-my-bids-title">
    <header class="zealot-market-activity__header"><div><h1 id="zealot-market-my-bids-title">My bids</h1><p>See whether you are leading, outbid or have already won a character.</p></div></header>
    <?php require SYSTEM . 'templates/zealot_market_nav.php'; ?>

    <?php if ($marketError) { ?>
        <div class="zealot-market-empty"><strong><?= htmlspecialchars($marketError, ENT_QUOTES, 'UTF-8'); ?></strong><?php if (!$logged) { ?><a href="<?= htmlspecialchars(getLink('account/manage'), ENT_QUOTES, 'UTF-8'); ?>">Log in</a><?php } ?></div>
    <?php } elseif (!$bids) { ?>
        <div class="zealot-market-empty"><strong>You have not placed any bids yet.</strong><span>Browse live character listings to find your next adventure.</span><a href="<?= htmlspecialchars(getLink('currentcharactertrades'), ENT_QUOTES, 'UTF-8'); ?>">Browse characters</a></div>
    <?php } else { ?>
        <table class="zealot-market-activity__table">
            <thead><tr><th>Character</th><th>Status</th><th>My bid</th><th>Ends / completed</th><th class="is-action">Action</th></tr></thead>
            <tbody>
            <?php foreach ($bids as $bid) {
                $open = (int) $bid['status'] === ZealotMarket::STATUS_ACTIVE && (int) $bid['date_end_unix'] > time();
                $status = $open ? ((int) $bid['is_winning'] === 1 ? 'Winning bid' : 'Outbid') : ZealotMarket::statusLabel((int) $bid['status']);
                $statusClass = $open && (int) $bid['is_winning'] === 1 ? 'live' : ((int) $bid['status'] === ZealotMarket::STATUS_SOLD ? 'sold' : 'closed');
                $dateSource = $open ? $bid['date_end'] : ($bid['completed_at'] ?: $bid['date_end']);
                $detailUrl = getLinkWithQuery('currentcharactertrades', ['details' => (int) $bid['id']]);
                ?>
                <tr>
                    <td data-label="Character"><strong><?= htmlspecialchars($bid['name'], ENT_QUOTES, 'UTF-8'); ?></strong><br><small><?= htmlspecialchars(siteText('Level'), ENT_QUOTES, 'UTF-8'); ?> <?= number_format((int) $bid['level']); ?> <?= htmlspecialchars(siteText($config['vocations'][$bid['vocation']] ?? 'Adventurer'), ENT_QUOTES, 'UTF-8'); ?></small></td>
                    <td data-label="Status"><span class="zealot-market-activity__status zealot-market-activity__status--<?= $statusClass; ?>"><?= htmlspecialchars(siteText($status), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-label="My bid"><?= number_format((int) $bid['my_bid']); ?> <img src="<?= $template_path; ?>/images/account/icon-tibiacointrusted.png" alt="Z"></td>
                    <td data-label="Ends / completed"><?= htmlspecialchars(siteDate('M j, Y' . ($open ? ' H:i' : ''), strtotime((string) $dateSource)), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="is-action" data-label="Action"><a class="zealot-market-activity__action" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8'); ?>">Access</a></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>
