<?php
defined('MYAAC') or die('Direct access not allowed!');

$marketNavActive = $marketNavActive ?? '';
$marketNavLinks = [
    'home' => ['label' => 'Market home', 'url' => getLink('exchange')],
    'browse' => ['label' => 'Browse', 'url' => getLink('currentcharactertrades')],
    'sell' => ['label' => 'Sell', 'url' => getLink('createcharacterauction')],
    'bids' => ['label' => 'My bids', 'url' => getLink('ownbids')],
    'listings' => ['label' => 'My listings', 'url' => getLink('owncharactertrades')],
    'history' => ['label' => 'History', 'url' => getLink('pastcharactertrades')],
];
?>
<nav class="zealot-market-nav" aria-label="Zealot Market quick access">
    <?php foreach ($marketNavLinks as $key => $link) { ?>
        <a class="<?= $marketNavActive === $key ? 'is-active' : ''; ?>" href="<?= htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'); ?>"<?= $marketNavActive === $key ? ' aria-current="page"' : ''; ?>><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></a>
    <?php } ?>
</nav>
