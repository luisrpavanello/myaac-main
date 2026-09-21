<?php

if (PHP_SAPI !== 'cli') {
    exit("This script can be run only from the command line.\n");
}

require_once __DIR__ . '/../../common.php';
require_once SYSTEM . 'functions.php';
require_once SYSTEM . 'init.php';
require_once LIBS . 'ZealotMarket.php';

$market = new ZealotMarket($db, $config);
$refreshActive = in_array('--refresh-active', $argv, true);
if ($refreshActive) {
    $updated = 0;
    foreach ($db->query('SELECT `id` FROM `myaac_charbazaar` WHERE `status` = ' . ZealotMarket::STATUS_ACTIVE) as $listing) {
        $updated += $market->refreshActiveListingSnapshot((int) $listing['id']) ? 1 : 0;
    }
} else {
    $updated = $market->backfillMissingSnapshots();
}

echo "Zealot Market snapshots updated: {$updated}\n";
