<?php
defined('MYAAC') or die('Direct access not allowed!');

$marketNavActive = $marketNavActive ?? '';
$marketNavLinks = [
    'home' => ['label' => t('market.nav.home'), 'url' => getLink('exchange')],
    'browse' => ['label' => t('market.nav.browse'), 'url' => getLink('currentcharactertrades')],
    'sell' => ['label' => t('market.nav.sell'), 'url' => getLink('createcharacterauction')],
    'bids' => ['label' => t('market.nav.bids'), 'url' => getLink('ownbids')],
    'listings' => ['label' => t('market.nav.listings'), 'url' => getLink('owncharactertrades')],
    'history' => ['label' => t('market.nav.history'), 'url' => getLink('pastcharactertrades')],
];
?>
<nav class="zealot-market-nav" aria-label="<?= htmlspecialchars(t('market.nav.label'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php foreach ($marketNavLinks as $key => $link) { ?>
        <a class="<?= $marketNavActive === $key ? 'is-active' : ''; ?>" href="<?= htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'); ?>"<?= $marketNavActive === $key ? ' aria-current="page"' : ''; ?>><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></a>
    <?php } ?>
</nav>
