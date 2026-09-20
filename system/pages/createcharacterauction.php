<?php
defined('MYAAC') or die('Direct access not allowed!');

require_once LIBS . 'ZealotMarket.php';

$title = 'Zealot Market — Sell a Character';
$market = new ZealotMarket($db, $config);
$marketCsrf = ZealotMarket::csrfToken();
$marketError = null;

if (!$logged) {
    $twig->display('account.login.html.twig', [
        'redirect' => getLink('createcharacterauction'),
        'account' => USE_ACCOUNT_NAME ? 'Name' : 'Number',
        'account_login_by' => getAccountLoginByLabel(),
        'error' => null,
    ]);
    return;
}

if (!$market->isInstalled()) {
    echo '<div class="zealot-market-empty"><strong>Zealot Market is not installed.</strong><span>Run the market installer before creating a listing.</span></div>';
    return;
}

$market->settleExpiredListings();
$sellerId = (int) $account_logged->getId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['market_action'] ?? '') === 'create_listing') {
    try {
        $market->verifyCsrf($_POST['market_csrf'] ?? null);
        $listingId = $market->createListing(
            $sellerId,
            (int) ($_POST['player_id'] ?? 0),
            (int) ($_POST['starting_price'] ?? 0),
            (int) ($_POST['duration_days'] ?? 0)
        );
        echo '<div class="zealot-market-feedback zealot-market-feedback--success">Your character is now listed in Zealot Market. <a href="?subtopic=currentcharactertrades&amp;details=' . $listingId . '">View listing</a></div>';
    } catch (Throwable $exception) {
        $marketError = $exception->getMessage();
    }
}

$account = $db->query('SELECT `coins_transferable` FROM `accounts` WHERE `id` = ' . $sellerId)->fetch();
$characters = $db->query(
    'SELECT p.`id`, p.`name`, p.`level`, p.`vocation` FROM `players` AS p '
    . 'WHERE p.`account_id` = ' . $sellerId . ' '
    . 'AND NOT EXISTS (SELECT 1 FROM `myaac_charbazaar` AS a WHERE a.`player_id` = p.`id` AND a.`status` = ' . ZealotMarket::STATUS_ACTIVE . ') '
    . 'ORDER BY p.`level` DESC, p.`name` ASC'
)->fetchAll();
$listingFee = max(0, (int) ($config['bazaar_create'] ?? 0));
$marketTax = max(0, min(100, (int) ($config['bazaar_tax'] ?? 0)));
?>

<section class="zealot-market-sell" aria-labelledby="zealot-market-sell-title">
    <header class="zealot-market-sell__header">
        <span>Zealot Market</span>
        <h1 id="zealot-market-sell-title">Sell a character</h1>
        <p>Your character is held safely in Market escrow for the duration of the listing. It is returned if the listing expires or is cancelled without bids.</p>
    </header>

    <div class="zealot-market-sell__balance">
        <span>Available transferable Zealot Coins</span>
        <strong><?= number_format((int) ($account['coins_transferable'] ?? 0)); ?> <img src="<?= $template_path; ?>/images/account/icon-tibiacointrusted.png" alt="Zealot Coins"></strong>
    </div>

    <?php if ($marketError) { ?>
        <div class="zealot-market-feedback zealot-market-feedback--error"><?= htmlspecialchars($marketError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>

    <?php if (!$characters) { ?>
        <div class="zealot-market-empty"><strong>No eligible characters are available.</strong><span>Characters currently listed in Zealot Market are kept in escrow until their listing ends.</span></div>
    <?php } else { ?>
        <form class="zealot-market-sell__form" method="post" action="<?= getLink('createcharacterauction'); ?>">
            <input type="hidden" name="market_action" value="create_listing">
            <input type="hidden" name="market_csrf" value="<?= htmlspecialchars($marketCsrf, ENT_QUOTES, 'UTF-8'); ?>">
            <label>
                <span>Character</span>
                <select name="player_id" required>
                    <?php foreach ($characters as $character) { ?>
                        <option value="<?= (int) $character['id']; ?>">
                            <?= htmlspecialchars($character['name'], ENT_QUOTES, 'UTF-8'); ?> — Level <?= (int) $character['level']; ?> <?= htmlspecialchars($config['vocations'][$character['vocation']] ?? 'Adventurer', ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
            <label>
                <span>Starting price</span>
                <input name="starting_price" type="number" min="1" required placeholder="Transferable Zealot Coins">
            </label>
            <label>
                <span>Listing duration</span>
                <select name="duration_days">
                    <option value="3">3 days</option>
                    <option value="7" selected>7 days</option>
                    <option value="14">14 days</option>
                    <option value="30">30 days</option>
                </select>
            </label>
            <div class="zealot-market-sell__rules">
                <strong>Market terms</strong>
                <span>Listing fee: <?= number_format($listingFee); ?> transferable Zealot Coins.</span>
                <span>Sale tax: <?= $marketTax; ?>% of the final bid.</span>
                <span>Once someone bids, the listing cannot be cancelled.</span>
            </div>
            <button type="submit">Create listing</button>
        </form>
    <?php } ?>
</section>
