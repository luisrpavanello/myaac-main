<?php
/** @var iterable $auctions */
defined('MYAAC') or die('Direct access not allowed!');

$marketListingCount = 0;

foreach ($auctions as $auction) {
    $characterQuery = $db->query(
        'SELECT `name`, `vocation`, `level`, `sex`, `cap` FROM `players` WHERE `id` = ' . (int) $auction['player_id']
    );
    $character = $characterQuery->fetch();

    if (!$character) {
        continue;
    }

    $snapshot = ZealotMarket::decodeCharacterSnapshot($auction['character_snapshot'] ?? null);
    $character = $snapshot['player'] ?? $character;

    $marketListingCount++;
    $characterName = htmlspecialchars((string) $character['name'], ENT_QUOTES, 'UTF-8');
    $vocationName = htmlspecialchars((string) ($config['vocations'][$character['vocation']] ?? 'Adventurer'), ENT_QUOTES, 'UTF-8');
    $genderName = htmlspecialchars((string) ($config['genders'][$character['sex']] ?? 'Unknown'), ENT_QUOTES, 'UTF-8');
    $outfitUrl = ZealotMarket::snapshotOutfitUrl($snapshot) ?? getVocationImage($character['vocation']);
    $detailUrl = getLinkWithQuery($subtopic, ['details' => (int) $auction['id']]);
    $currentBid = max((int) $auction['price'], (int) ($auction['bid_price'] ?? 0));
    $endsAt = (int) ($auction['date_end_unix'] ?? strtotime($auction['date_end']));
    $secondsLeft = max(0, $endsAt - time());
    $daysLeft = intdiv($secondsLeft, 86400);
    $hoursLeft = intdiv($secondsLeft % 86400, 3600);
    $minutesLeft = intdiv($secondsLeft % 3600, 60);
    $timeLeft = $daysLeft > 0 ? $daysLeft . 'd ' . $hoursLeft . 'h left' : $hoursLeft . 'h ' . $minutesLeft . 'm left';

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
        $equipmentQuery = $db->query(
            'SELECT `pid`, `itemtype` FROM `player_items` WHERE `player_id` = ' . (int) $auction['player_id'] . ' AND `pid` BETWEEN 1 AND 10'
        );
        foreach ($equipmentQuery as $item) {
            if ((int) $item['itemtype'] > 0) {
                $equipment[(int) $item['pid']] = (int) $item['itemtype'];
            }
        }
    }
    ?>
    <article class="zealot-market-card">
        <header class="zealot-market-card__header">
            <div>
                <span class="zealot-market-card__eyebrow">Live listing</span>
                <h2><a href="<?= $detailUrl; ?>"><?= $characterName; ?></a></h2>
            </div>
            <span class="zealot-market-card__timer" title="Ends <?= htmlspecialchars(date('M j, Y H:i', $endsAt), ENT_QUOTES, 'UTF-8'); ?>"><?= $timeLeft; ?></span>
        </header>

        <div class="zealot-market-card__body">
            <div class="zealot-market-card__outfit">
                <img src="<?= htmlspecialchars($outfitUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= $vocationName; ?> outfit">
                <strong>Level <?= number_format((int) $character['level']); ?></strong>
            </div>

            <dl class="zealot-market-card__details">
                <div><dt>Vocation</dt><dd><?= $vocationName; ?></dd></div>
                <div><dt>Gender</dt><dd><?= $genderName; ?></dd></div>
                <div><dt>Capacity</dt><dd><?= number_format((int) $character['cap']); ?></dd></div>
                <div><dt>Listed</dt><dd><?= htmlspecialchars(date('M j, Y', (int) ($auction['date_start_unix'] ?? strtotime($auction['date_start']))), ENT_QUOTES, 'UTF-8'); ?></dd></div>
            </dl>

            <div class="zealot-market-card__equipment" aria-label="Visible equipment">
                <?php foreach (range(1, 10) as $slot) {
                    if (isset($equipment[$slot])) { ?>
                        <span><?= getItemImage((int) (is_array($equipment[$slot]) ? $equipment[$slot]['itemtype'] : $equipment[$slot]), (int) (is_array($equipment[$slot]) ? $equipment[$slot]['count'] : 1), $equipmentFallbacks[$slot]); ?></span>
                    <?php } else { ?>
                        <span class="zealot-market-card__empty-slot"><?= getItemImage(0, 1, $equipmentFallbacks[$slot]); ?></span>
                    <?php }
                } ?>
            </div>
        </div>

        <footer class="zealot-market-card__footer">
            <div>
                <span>Current bid</span>
                <strong><?= number_format($currentBid); ?> <img src="<?= $template_path; ?>/images/account/icon-tibiacointrusted.png" alt="Zealot Coins"></strong>
            </div>
            <a href="<?= $detailUrl; ?>">View character <span aria-hidden="true">→</span></a>
        </footer>
    </article>
<?php }

if ($marketListingCount === 0) { ?>
    <div class="zealot-market-empty">
        <strong>No characters match these filters.</strong>
        <span>Try changing the filters or be the first player to create a listing.</span>
        <a href="<?= $logged ? getLink('createcharacterauction') : getLink('account/manage'); ?>"><?= $logged ? 'Sell a character' : 'Log in to sell'; ?></a>
    </div>
<?php } ?>
