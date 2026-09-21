<?php

/**
 * Lightweight, uncached player-count endpoint for the public Daily Boosts
 * card. It intentionally reads the game-maintained online table directly
 * instead of the site status cache.
 */
define('MYAAC_NO_SESSION', true);
require_once __DIR__ . '/../common.php';
require_once SYSTEM . 'functions.php';
require_once SYSTEM . 'init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$playersOnline = 0;
$source = 'unavailable';

if ($db->hasTable('players_online')) {
    $online = $db->query('SELECT COUNT(`player_id`) AS `total` FROM `players_online`')->fetch();
    $playersOnline = (int) ($online['total'] ?? 0);
    $source = 'players_online';
} elseif ($db->hasColumn('players', 'online')) {
    $online = $db->query('SELECT COUNT(`id`) AS `total` FROM `players` WHERE `online` > 0')->fetch();
    $playersOnline = (int) ($online['total'] ?? 0);
    $source = 'players.online';
}

echo json_encode([
    'players' => $playersOnline,
    'source' => $source,
    'updatedAt' => time(),
], JSON_UNESCAPED_SLASHES);
