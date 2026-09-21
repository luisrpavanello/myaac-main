<?php
defined('MYAAC') or die('Direct access not allowed!');

require_once LIBS . 'ZealotMarket.php';

$title = 'Zealot Market — History';
$market = new ZealotMarket($db, $config);
$marketNavActive = 'history';
$viewerId = $logged ? (int) $account_logged->getId() : 0;
$marketCsrf = ZealotMarket::csrfToken();
$history = [];
$marketError = null;
$marketMessage = null;

if (!$market->isInstalled()) {
    $marketError = 'Zealot Market has not been installed yet.';
} else {
    $market->settleExpiredListings();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['market_action'] ?? '') === 'hide_history') {
        try {
            if (!$logged) {
                throw new RuntimeException('Log in to hide an entry from your personal Market history.');
            }
            $market->verifyCsrf($_POST['market_csrf'] ?? null);
            $market->hideHistoryListing($viewerId, (int) ($_POST['auction_id'] ?? 0));
            $marketMessage = 'This listing has been hidden from your Market history.';
        } catch (Throwable $exception) {
            $marketError = $exception->getMessage();
        }
    }

    $history = $db->query(
        'SELECT a.*, p.`name`, p.`level`, p.`vocation` '
        . 'FROM `myaac_charbazaar` AS a '
        . 'INNER JOIN `players` AS p ON p.`id` = a.`player_id` '
        . 'LEFT JOIN `myaac_zealot_market_hidden_history` AS h ON h.`auction_id` = a.`id` AND h.`account_id` = ' . $viewerId . ' '
        . 'WHERE a.`status` IN (' . ZealotMarket::STATUS_SOLD . ', ' . ZealotMarket::STATUS_CANCELLED . ', ' . ZealotMarket::STATUS_EXPIRED . ') '
        . 'AND h.`auction_id` IS NULL '
        . 'ORDER BY COALESCE(a.`completed_at`, a.`cancelled_at`, a.`date_end`) DESC, a.`id` DESC'
    )->fetchAll();
}
?>
<section class="zealot-market-activity" aria-labelledby="zealot-market-history-title">
    <header class="zealot-market-activity__header">
        <div>
            <h1 id="zealot-market-history-title">Market history</h1>
            <p>Browse completed sales and safely returned characters from Zealot Market.</p>
        </div>
        <a class="zealot-market-activity__action" href="<?= htmlspecialchars(getLink('currentcharactertrades'), ENT_QUOTES, 'UTF-8'); ?>">Browse characters</a>
    </header>
    <?php require SYSTEM . 'templates/zealot_market_nav.php'; ?>

    <?php if ($marketMessage) { ?>
        <div class="zealot-market-feedback zealot-market-feedback--success"><?= htmlspecialchars($marketMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>
    <?php if ($marketError) { ?>
        <div class="zealot-market-empty"><strong><?= htmlspecialchars($marketError, ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <?php } elseif (!$history) { ?>
        <div class="zealot-market-empty"><strong>There are no completed Market listings yet.</strong><span>Finished sales, cancelled listings and expired listings will appear here.</span><a href="<?= htmlspecialchars(getLink('currentcharactertrades'), ENT_QUOTES, 'UTF-8'); ?>">Browse characters</a></div>
    <?php } else { ?>
        <table class="zealot-market-activity__table">
            <thead><tr><th>Character</th><th>Result</th><th>Final bid</th><th>Completed</th><th class="is-action">Action</th></tr></thead>
            <tbody>
            <?php foreach ($history as $listing) {
                $status = ZealotMarket::statusLabel((int) $listing['status']);
                $statusClass = (int) $listing['status'] === ZealotMarket::STATUS_SOLD ? 'sold' : 'closed';
                $amount = (int) $listing['status'] === ZealotMarket::STATUS_SOLD
                    ? (int) $listing['final_price']
                    : max((int) $listing['starting_price'], (int) $listing['bid_price']);
                $dateSource = $listing['completed_at'] ?: ($listing['cancelled_at'] ?: $listing['date_end']);
                $detailUrl = getLinkWithQuery('currentcharactertrades', ['details' => (int) $listing['id']]);
                ?>
                <tr>
                    <td data-label="Character"><strong><?= htmlspecialchars($listing['name'], ENT_QUOTES, 'UTF-8'); ?></strong><br><small>Level <?= number_format((int) $listing['level']); ?> <?= htmlspecialchars($config['vocations'][$listing['vocation']] ?? 'Adventurer', ENT_QUOTES, 'UTF-8'); ?></small></td>
                    <td data-label="Result"><span class="zealot-market-activity__status zealot-market-activity__status--<?= $statusClass; ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-label="Final bid"><?= $amount > 0 ? number_format($amount) . ' <img src="' . htmlspecialchars($template_path, ENT_QUOTES, 'UTF-8') . '/images/account/icon-tibiacointrusted.png" alt="Z">' : '—'; ?></td>
                    <td data-label="Completed"><?= htmlspecialchars(date('M j, Y', strtotime((string) $dateSource)), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="is-action" data-label="Action">
                        <div class="zealot-market-activity__actions">
                            <a class="zealot-market-activity__action" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8'); ?>">Access</a>
                            <?php if ($logged) { ?>
                                <form method="post" action="<?= htmlspecialchars(getLink('pastcharactertrades'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="market_action" value="hide_history">
                                    <input type="hidden" name="market_csrf" value="<?= htmlspecialchars($marketCsrf, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="auction_id" value="<?= (int) $listing['id']; ?>">
                                    <button class="zealot-market-activity__hide" type="submit">Hide for me</button>
                                </form>
                            <?php } ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>
