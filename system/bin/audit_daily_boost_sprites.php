<?php

if (PHP_SAPI !== 'cli') {
    exit("This script can be run only from the command line.\n");
}

require_once __DIR__ . '/../../common.php';
require_once SYSTEM . 'functions.php';
require_once SYSTEM . 'init.php';

$sources = [
    'Creature' => 'boosted_creature',
    'Boss' => 'boosted_boss',
];
$missing = 0;

foreach ($sources as $label => $table) {
    if (!$db->hasTable($table)) {
        fwrite(STDERR, "SKIPPED  {$label}: table {$table} does not exist\n");
        continue;
    }

    $hasLookTypeEx = $db->hasColumn($table, 'looktypeEx');
    $boost = $db->query('SELECT `boostname`, `looktype`' . ($hasLookTypeEx ? ', `looktypeEx`' : '') . ' FROM `' . $table . '` LIMIT 1')->fetch();
    $name = trim((string) ($boost['boostname'] ?? ''));
    $lookType = (int) ($boost['looktype'] ?? 0);
    $lookTypeEx = (int) ($boost['looktypeEx'] ?? 0);
    if ($name === '') {
        fwrite(STDERR, "MISSING  {$label}: no boosted creature configured\n");
        $missing++;
        continue;
    }

    $sprite = getDailyBoostSpriteUrl($name, $lookType, $lookTypeEx);
    if ($sprite === null) {
        $identifier = $lookType > 0 ? 'looktype ' . $lookType : 'looktypeEx ' . $lookTypeEx;
        fwrite(STDERR, "MISSING  {$label}: {$name} ({$identifier})\n");
        fwrite(STDERR, "         Run: php system/bin/sync_daily_boost_sprites.php\n");
        $missing++;
        continue;
    }

    echo "OK       {$label}: {$name} -> {$sprite}\n";
}

exit($missing === 0 ? 0 : 1);
