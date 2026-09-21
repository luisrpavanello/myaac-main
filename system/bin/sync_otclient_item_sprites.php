#!/usr/bin/env php
<?php

/**
 * Populate the website item icon cache from the official OTClient assets.
 *
 * Examples:
 *   php system/bin/sync_otclient_item_sprites.php --used
 *   php system/bin/sync_otclient_item_sprites.php --all --limit=500
 *   php system/bin/sync_otclient_item_sprites.php --ids=3281,3372 --force
 */

if (PHP_SAPI !== 'cli') {
    exit("This script can be run only from the command line.\n");
}

require_once __DIR__ . '/../../common.php';
require_once SYSTEM . 'functions.php';
require_once SYSTEM . 'init.php';
require_once LIBS . 'OtClientItemSprites.php';

$arguments = $argv;
array_shift($arguments);
$all = in_array('--all', $arguments, true);
$force = in_array('--force', $arguments, true);
$idsArgument = null;
$limit = 0;
foreach ($arguments as $argument) {
    if (strpos($argument, '--ids=') === 0) {
        $idsArgument = substr($argument, 6);
    }
    if (strpos($argument, '--limit=') === 0) {
        $limit = max(0, (int) substr($argument, 8));
    }
}

$ids = [];
if ($idsArgument !== null) {
    foreach (explode(',', $idsArgument) as $itemId) {
        $itemId = (int) trim($itemId);
        if ($itemId > 0) {
            $ids[$itemId] = true;
        }
    }
} elseif ($all) {
    $itemsFile = getenv('ZEALOT_SERVER_ITEMS_XML') ?: dirname(BASE) . '/canary/data/items/items.xml';
    if (!is_file($itemsFile)) {
        fwrite(STDERR, "Server items.xml was not found. Set ZEALOT_SERVER_ITEMS_XML.\n");
        exit(1);
    }
    $xml = file_get_contents($itemsFile);
    preg_match_all('/<item\\b([^>]*)>/i', (string) $xml, $items);
    foreach ($items[1] as $attributes) {
        preg_match('/\\bid="(\\d+)"/i', $attributes, $single);
        preg_match('/\\bfromid="(\\d+)"/i', $attributes, $from);
        preg_match('/\\btoid="(\\d+)"/i', $attributes, $to);
        if ($single) {
            $ids[(int) $single[1]] = true;
        } elseif ($from && $to) {
            foreach (range((int) $from[1], (int) $to[1]) as $itemId) {
                $ids[$itemId] = true;
            }
        }
    }
} else {
    foreach (['player_items', 'player_depotitems', 'player_inboxitems'] as $table) {
        if (!$db->hasTable($table)) {
            continue;
        }
        foreach ($db->query('SELECT DISTINCT `itemtype` FROM `' . $table . '` WHERE `itemtype` > 0') as $row) {
            $ids[(int) $row['itemtype']] = true;
        }
    }
}

$ids = array_keys($ids);
sort($ids, SORT_NUMERIC);
if ($limit > 0) {
    $ids = array_slice($ids, 0, $limit);
}

$exported = 0;
$missing = 0;
foreach ($ids as $itemId) {
    $path = OtClientItemSprites::ensure($itemId, $force);
    if ($path) {
        $exported++;
        echo "OK       {$itemId}  {$path}\n";
    } else {
        $missing++;
        echo "MISSING  {$itemId}\n";
    }
}

echo sprintf("Completed: %d official sprites, %d unavailable, %d total.\n", $exported, $missing, count($ids));
exit($missing === 0 ? 0 : 1);
