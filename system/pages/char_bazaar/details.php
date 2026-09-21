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
$snapshot = ZealotMarket::decodeCharacterSnapshot($listing['character_snapshot'] ?? null);
$character = $snapshot['player'] ?? $listing;
$vocation = $config['vocations'][$character['vocation']] ?? 'Adventurer';
$gender = $config['genders'][$character['sex']] ?? ((int) $character['sex'] === 0 ? 'Male' : 'Female');
$outfitUrl = ZealotMarket::snapshotOutfitUrl($snapshot) ?? getVocationImage($character['vocation']);

$equipment = ZealotMarket::snapshotEquipment($snapshot);
$equipmentFallbacks = [
    1 => 'no_helmet.gif',
    2 => 'no_necklace.gif',
    3 => 'no_backpack.gif',
    4 => 'no_armor.gif',
    5 => 'no_handright.gif',
    6 => 'no_handleft.gif',
    7 => 'no_legs.gif',
    8 => 'no_boots.gif',
    9 => 'no_ring.gif',
    10 => 'no_ammo.gif',
];
if (!$snapshot && $db->hasTable('player_items')) {
    foreach ($db->query('SELECT `pid`, `itemtype` FROM `player_items` WHERE `player_id` = ' . (int) $listing['player_id'] . ' AND `pid` BETWEEN 1 AND 10') as $item) {
        if ((int) $item['itemtype'] > 0) {
            $equipment[(int) $item['pid']] = (int) $item['itemtype'];
        }
    }
}

$backpackItems = ZealotMarket::snapshotItemTree($snapshot, 'player_items', [3]);
$depotItems = ZealotMarket::snapshotItemTree($snapshot, 'depot_items', range(0, 99));
$renderItemTree = static function (array $items) use (&$renderItemTree): void {
    if (!$items) {
        echo '<p class="zealot-market-inventory__empty">No items recorded.</p>';
        return;
    }

    echo '<ul>';
    foreach ($items as $item) {
        $itemType = (int) ($item['itemtype'] ?? 0);
        $count = max(1, (int) ($item['count'] ?? 1));
        $name = getItemNameById($itemType) ?: 'Item #' . $itemType;
        echo '<li><span class="zealot-market-inventory__item">' . getItemImage($itemType, $count)
            . '<span>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ($count > 1 ? ' <b>×' . $count . '</b>' : '') . '</span></span>';
        $renderItemTree($item['children'] ?? []);
        echo '</li>';
    }
    echo '</ul>';
};

$blessings = 0;
for ($number = 1; $number <= 7; $number++) {
    $blessings += (int) (($character['blessings' . $number] ?? 0) > 0);
}
$statusLabel = $isActive ? 'Live listing' : ZealotMarket::statusLabel((int) $listing['status']);
$minimumBid = $market instanceof ZealotMarket ? $market->minimumBidFor($listing) : $currentBid + 1;
?>

<section class="zealot-market-detail" aria-labelledby="zealot-market-character">
    <a class="zealot-market-detail__back" href="?subtopic=currentcharactertrades">← Back to characters for sale</a>
    <?php $marketNavActive = 'browse'; require SYSTEM . 'templates/zealot_market_nav.php'; ?>
    <header class="zealot-market-detail__header">
        <div>
            <span><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <h1 id="zealot-market-character"><?= htmlspecialchars($character['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Level <?= number_format((int) $character['level']); ?> <?= htmlspecialchars($vocation, ENT_QUOTES, 'UTF-8'); ?> · <?= htmlspecialchars($gender, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="zealot-market-detail__timer">
            <small><?= $isActive ? 'Time remaining' : 'Listing status'; ?></small>
            <strong><?= $isActive ? $timeLeft . ' left' : htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
    </header>

    <div class="zealot-market-detail__main">
        <div class="zealot-market-detail__outfit">
            <img src="<?= htmlspecialchars($outfitUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($vocation, ENT_QUOTES, 'UTF-8'); ?> outfit">
            <span>Character preview</span>
        </div>

        <dl class="zealot-market-detail__facts">
            <div><dt>Health</dt><dd><?= number_format((int) $character['health']); ?> / <?= number_format((int) $character['healthmax']); ?></dd></div>
            <div><dt>Mana</dt><dd><?= number_format((int) $character['mana']); ?> / <?= number_format((int) $character['manamax']); ?></dd></div>
            <div><dt>Capacity</dt><dd><?= number_format((int) $character['cap']); ?></dd></div>
            <div><dt>Soul</dt><dd><?= number_format((int) $character['soul']); ?></dd></div>
            <div><dt>Magic level</dt><dd><?= number_format((int) $character['maglevel']); ?></dd></div>
            <div><dt>Blessings</dt><dd><?= $blessings; ?> / 7<?= (int) $character['blessings8'] > 0 ? ' + Twist' : ''; ?></dd></div>
        </dl>

        <div class="zealot-market-detail__equipment" aria-label="Character equipment">
            <?php foreach (range(1, 10) as $slot) { ?>
                <span class="<?= isset($equipment[$slot]) ? '' : 'is-empty'; ?>">
                    <?= isset($equipment[$slot]) ? getItemImage((int) (is_array($equipment[$slot]) ? $equipment[$slot]['itemtype'] : $equipment[$slot]), (int) (is_array($equipment[$slot]) ? $equipment[$slot]['count'] : 1), $equipmentFallbacks[$slot]) : getItemImage(0, 1, $equipmentFallbacks[$slot]); ?>
                </span>
            <?php } ?>
        </div>
    </div>

    <section class="zealot-market-inventory" aria-label="Included character inventory">
        <header>
            <div>
                <span>Included with this character</span>
                <h2>Inventory snapshot</h2>
            </div>
            <small><?= $snapshot ? 'Captured when this listing was created' : 'Legacy listing — inventory snapshot unavailable'; ?></small>
        </header>
        <div class="zealot-market-inventory__groups">
            <details open>
                <summary>Backpack<?= $backpackItems ? '' : ' — empty'; ?></summary>
                <?php $renderItemTree($backpackItems); ?>
            </details>
            <details>
                <summary>Depot<?= $depotItems ? '' : ' — empty'; ?></summary>
                <?php $renderItemTree($depotItems); ?>
            </details>
        </div>
    </section>

    <div class="zealot-market-detail__bottom">
        <section class="zealot-market-detail__skills">
            <h2>Skills</h2>
            <?php foreach ([
                'Fist' => 'skill_fist', 'Club' => 'skill_club', 'Sword' => 'skill_sword', 'Axe' => 'skill_axe',
                'Distance' => 'skill_dist', 'Shielding' => 'skill_shielding', 'Fishing' => 'skill_fishing',
            ] as $label => $field) { ?>
                <div><span><?= $label; ?></span><strong><?= number_format((int) $character[$field]); ?></strong></div>
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
                    <input id="zealot-market-bid" name="maxbid" type="number" min="<?= $minimumBid; ?>" required placeholder="At least <?= number_format($minimumBid); ?>">
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
