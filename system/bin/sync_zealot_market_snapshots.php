<?php

if (PHP_SAPI !== 'cli') {
    exit("This script can be run only from the command line.\n");
}

require_once __DIR__ . '/../../common.php';
require_once SYSTEM . 'functions.php';
require_once SYSTEM . 'init.php';
require_once LIBS . 'ZealotMarket.php';

$market = new ZealotMarket($db, $config);
$updated = $market->backfillMissingSnapshots();

echo "Zealot Market snapshots updated: {$updated}\n";
