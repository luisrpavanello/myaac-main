<?php

defined('MYAAC') or die('Direct access not allowed!');

global $config, $db, $template_path;

$listingId = (int) ($getPageDetails ?? 0);
$listing = $db->query(
    'SELECT a.*, UNIX_TIMESTAMP(a.`date_end`) AS `date_end_unix`, UNIX_TIMESTAMP(a.`date_start`) AS `date_start_unix`, '
    . 'p.`name`, p.`vocation`, p.`level`, p.`sex`, p.`health`, p.`healthmax`, p.`mana`, p.`manamax`, p.`maglevel`, '
    . 'p.`cap`, p.`soul`, p.`experience`, p.`balance`, p.`skill_fist`, p.`skill_club`, p.`skill_sword`, p.`skill_axe`, '
    . 'p.`skill_dist`, p.`skill_shielding`, p.`skill_fishing`, p.`blessings1`, p.`blessings2`, p.`blessings3`, '
    . 'p.`blessings4`, p.`blessings5`, p.`blessings6`, p.`blessings7`, p.`blessings8` '
    . 'FROM `myaac_charbazaar` AS a INNER JOIN `players` AS p ON p.`id` = a.`player_id` '
    . 'WHERE a.`id` = ' . $listingId
)->fetch();

if (!$listing) {
    echo '<div class="zealot-market-empty"><strong>Listing not found.</strong><a href="?subtopic=currentcharactertrades">Back to Zealot Market</a></div>';
    return;
}

$viewerId = $logged ? (int) $account_logged->getId() : 0;
$isActive = (int) $listing['status'] === ZealotMarket::STATUS_ACTIVE && (int) $listing['date_end_unix'] > time();
$isSeller = $viewerId > 0 && $viewerId === (int) $listing['account_old'];
$isWinningBidder = $viewerId > 0 && $viewerId === (int) $listing['bid_account'];
$currentBid = max((int) $listing['starting_price'], (int) $listing['bid_price'], (int) $listing['price']);
$secondsLeft = max(0, (int) $listing['date_end_unix'] - time());
$timeLeft = intdiv($secondsLeft, 86400) > 0
    ? intdiv($secondsLeft, 86400) . 'd ' . intdiv($secondsLeft % 86400, 3600) . 'h'
    : intdiv($secondsLeft, 3600) . 'h ' . intdiv($secondsLeft % 3600, 60) . 'm';
$vocation = $config['vocations'][$listing['vocation']] ?? 'Adventurer';
$gender = $config['genders'][$listing['sex']] ?? ((int) $listing['sex'] === 0 ? 'Male' : 'Female');

$equipment = [];
if ($db->hasTable('player_items')) {
    foreach ($db->query('SELECT `pid`, `itemtype` FROM `player_items` WHERE `player_id` = ' . (int) $listing['player_id'] . ' AND `pid` BETWEEN 1 AND 10') as $item) {
        if ((int) $item['itemtype'] > 0) {
            $equipment[(int) $item['pid']] = (int) $item['itemtype'];
        }
    }
}

$blessings = 0;
for ($number = 1; $number <= 7; $number++) {
    $blessings += (int) ($listing['blessings' . $number] > 0);
}
$statusLabel = match ((int) $listing['status']) {
    ZealotMarket::STATUS_SOLD => 'Sold',
    ZealotMarket::STATUS_CANCELLED => 'Cancelled',
    ZealotMarket::STATUS_EXPIRED => 'Expired',
    default => $isActive ? 'Live listing' : 'Awaiting settlement',
};
?>

<section class="zealot-market-detail" aria-labelledby="zealot-market-character">
    <a class="zealot-market-detail__back" href="?subtopic=currentcharactertrades">← Back to characters for sale</a>
    <header class="zealot-market-detail__header">
        <div>
            <span><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <h1 id="zealot-market-character"><?= htmlspecialchars($listing['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Level <?= number_format((int) $listing['level']); ?> <?= htmlspecialchars($vocation, ENT_QUOTES, 'UTF-8'); ?> · <?= htmlspecialchars($gender, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="zealot-market-detail__timer">
            <small><?= $isActive ? 'Time remaining' : 'Listing status'; ?></small>
            <strong><?= $isActive ? $timeLeft . ' left' : htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
    </header>

    <div class="zealot-market-detail__main">
        <div class="zealot-market-detail__outfit">
            <img src="<?= htmlspecialchars(getVocationImage($listing['vocation']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($vocation, ENT_QUOTES, 'UTF-8'); ?> outfit">
            <span>Character preview</span>
        </div>

        <dl class="zealot-market-detail__facts">
            <div><dt>Health</dt><dd><?= number_format((int) $listing['health']); ?> / <?= number_format((int) $listing['healthmax']); ?></dd></div>
            <div><dt>Mana</dt><dd><?= number_format((int) $listing['mana']); ?> / <?= number_format((int) $listing['manamax']); ?></dd></div>
            <div><dt>Capacity</dt><dd><?= number_format((int) $listing['cap']); ?></dd></div>
            <div><dt>Soul</dt><dd><?= number_format((int) $listing['soul']); ?></dd></div>
            <div><dt>Magic level</dt><dd><?= number_format((int) $listing['maglevel']); ?></dd></div>
            <div><dt>Blessings</dt><dd><?= $blessings; ?> / 7<?= (int) $listing['blessings8'] > 0 ? ' + Twist' : ''; ?></dd></div>
        </dl>

        <div class="zealot-market-detail__equipment" aria-label="Character equipment">
            <?php foreach ([1, 4, 5, 6, 7, 8] as $slot) { ?>
                <span class="<?= isset($equipment[$slot]) ? '' : 'is-empty'; ?>">
                    <?= isset($equipment[$slot]) ? getItemImage($equipment[$slot]) : '—'; ?>
                </span>
            <?php } ?>
        </div>
    </div>

    <div class="zealot-market-detail__bottom">
        <section class="zealot-market-detail__skills">
            <h2>Skills</h2>
            <?php foreach ([
                'Fist' => 'skill_fist', 'Club' => 'skill_club', 'Sword' => 'skill_sword', 'Axe' => 'skill_axe',
                'Distance' => 'skill_dist', 'Shielding' => 'skill_shielding', 'Fishing' => 'skill_fishing',
            ] as $label => $field) { ?>
                <div><span><?= $label; ?></span><strong><?= number_format((int) $listing[$field]); ?></strong></div>
            <?php } ?>
        </section>

        <aside class="zealot-market-detail__purchase">
            <span>Current bid</span>
            <strong><?= number_format($currentBid); ?> <img src="<?= $template_path; ?>/images/account/icon-tibiacointrusted.png" alt="Zealot Coins"></strong>
            <small>Started at <?= number_format((int) $listing['starting_price']); ?> · Ends <?= htmlspecialchars(date('M j, Y H:i', (int) $listing['date_end_unix']), ENT_QUOTES, 'UTF-8'); ?></small>

            <?php if (!$logged && $isActive) { ?>
                <a href="?subtopic=account/manage">Log in to bid</a>
            <?php } elseif ($isActive && !$isSeller) { ?>
                <form action="?subtopic=currentcharactertrades&amp;action=bid" method="post">
                    <input type="hidden" name="market_csrf" value="<?= htmlspecialchars($marketCsrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="auction_iden" value="<?= (int) $listing['id']; ?>">
                    <label for="zealot-market-bid">Your bid</label>
                    <input id="zealot-market-bid" name="maxbid" type="number" min="<?= $currentBid + 1; ?>" required placeholder="At least <?= number_format($currentBid + 1); ?>">
                    <button type="submit"><?= $isWinningBidder ? 'Increase bid' : 'Place bid'; ?></button>
                </form>
            <?php } elseif ($isActive && $isSeller && (int) $listing['bid_account'] === 0) { ?>
                <form action="?subtopic=currentcharactertrades&amp;action=cancel" method="post">
                    <input type="hidden" name="market_csrf" value="<?= htmlspecialchars($marketCsrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="auction_iden" value="<?= (int) $listing['id']; ?>">
                    <p>Your character is protected in Zealot Market escrow.</p>
                    <button type="submit">Cancel listing</button>
                </form>
            <?php } elseif ($isSeller && (int) $listing['bid_account'] > 0) { ?>
                <p>Your listing has a bid and cannot be cancelled.</p>
            <?php } else { ?>
                <p>This listing is no longer accepting bids.</p>
            <?php } ?>
        </aside>
    </div>
</section>
