<?php

if (PHP_SAPI !== 'cli') {
    exit("This script can be run only from the command line.\n");
}

$onlyMissing = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--if-missing') {
        $onlyMissing = true;
        continue;
    }
    if ($argument === '--help' || $argument === '-h') {
        echo "Usage: php system/bin/sync_daily_boost_sprites.php [--if-missing]\n";
        echo "  --if-missing  Export only the sprites that are not already cached.\n";
        exit(0);
    }
    fwrite(STDERR, "Unknown option: {$argument}\n");
    exit(64);
}

require_once __DIR__ . '/../../common.php';
require_once SYSTEM . 'functions.php';
require_once SYSTEM . 'init.php';

$assetsPath = getenv('ZEALOT_OTCLIENT_ASSETS');
if (!$assetsPath) {
    $thingsRoot = dirname(BASE) . '/otclient/data/things';
    $versions = glob($thingsRoot . '/*', GLOB_ONLYDIR) ?: [];
    usort($versions, static function ($left, $right) {
        return strnatcasecmp(basename($right), basename($left));
    });
    foreach ($versions as $versionPath) {
        if (is_file($versionPath . '/catalog-content.json')) {
            $assetsPath = $versionPath;
            break;
        }
    }
}
$assetsPath = rtrim((string) $assetsPath, '/');
$exporter = __DIR__ . '/export_otclient_sprite.py';
$outputDirectory = BASE . 'images/library/daily-boost';

if (!is_file($exporter)) {
    fwrite(STDERR, "Missing exporter: {$exporter}\n");
    exit(1);
}
if (!is_file($assetsPath . '/catalog-content.json')) {
    fwrite(STDERR, "OTClient assets were not found at {$assetsPath}.\n");
    fwrite(STDERR, "Set ZEALOT_OTCLIENT_ASSETS to your client data/things/<version> directory.\n");
    exit(1);
}

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0755, true) && !is_dir($outputDirectory)) {
    fwrite(STDERR, "Unable to create {$outputDirectory}.\n");
    exit(1);
}

$sources = [
    'creature' => 'boosted_creature',
    'boss' => 'boosted_boss',
];
$failed = 0;

function isCurrentDailyBoostSprite(string $file): bool
{
    if (!is_file($file) || filesize($file) <= 0) {
        return false;
    }

    // The exporter stamps v3 into the GIF. This makes --if-missing also heal
    // caches created before a rendering fix without continuously re-exporting
    // current-format sprites every five minutes.
    $header = file_get_contents($file, false, null, 0, 1024);
    return is_string($header) && strpos($header, 'DAILYBOOSTv3') !== false;
}

foreach ($sources as $label => $table) {
    if (!$db->hasTable($table)) {
        fwrite(STDERR, "SKIPPED  {$label}: table {$table} does not exist\n");
        continue;
    }

    $hasLookTypeEx = $db->hasColumn($table, 'looktypeEx');
    $boost = $db->query(
        'SELECT `boostname`, `looktype`, `lookhead`, `lookbody`, `looklegs`, `lookfeet`, `lookaddons`'
        . ($hasLookTypeEx ? ', `looktypeEx`' : '')
        . ' FROM `' . $table . '` LIMIT 1'
    )->fetch();
    $lookType = (int) ($boost['looktype'] ?? 0);
    $lookTypeEx = (int) ($boost['looktypeEx'] ?? 0);
    $category = 'outfit';
    if ($lookType <= 0 && $lookTypeEx > 0) {
        $lookType = $lookTypeEx;
        $category = 'object';
    }
    if ($lookType <= 0) {
        fwrite(STDERR, "MISSING  {$label}: no official looktype is stored\n");
        $failed++;
        continue;
    }

    // Daily Boosts intentionally use the client animation, not a screenshot of
    // its first frame. Keeping the files keyed by looktype makes a new daily
    // creature/boss deterministic and independent from its display name.
    $outputFile = $outputDirectory . '/' . ($category === 'object' ? 'item-' : '') . $lookType . '.gif';
    if ($onlyMissing && isCurrentDailyBoostSprite($outputFile)) {
        echo strtoupper($label) . "  SKIPPED {$outputFile} already exists\n";
        continue;
    }
    $arguments = [
        'python3',
        $exporter,
        '--assets', $assetsPath,
        '--looktype', (string) $lookType,
        '--category', $category,
        '--output', $outputFile,
        '--animated',
        '--direction', $category === 'outfit' ? '2' : '0',
        '--head', (string) ((int) ($boost['lookhead'] ?? 0)),
        '--body', (string) ((int) ($boost['lookbody'] ?? 0)),
        '--legs', (string) ((int) ($boost['looklegs'] ?? 0)),
        '--feet', (string) ((int) ($boost['lookfeet'] ?? 0)),
        '--addons', (string) ((int) ($boost['lookaddons'] ?? 0)),
    ];
    $command = implode(' ', array_map('escapeshellarg', $arguments));
    $output = [];
    exec($command . ' 2>&1', $output, $status);
    foreach ($output as $line) {
        echo strtoupper($label) . '  ' . $line . PHP_EOL;
    }
    if ($status !== 0) {
        $failed++;
    }
}

exit($failed === 0 ? 0 : 1);
