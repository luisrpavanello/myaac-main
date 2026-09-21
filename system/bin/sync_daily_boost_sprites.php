<?php

if (PHP_SAPI !== 'cli') {
    exit("This script can be run only from the command line.\n");
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

    $outputFile = $outputDirectory . '/' . ($category === 'object' ? 'item-' : '') . $lookType . '.png';
    $arguments = [
        'python3',
        $exporter,
        '--assets', $assetsPath,
        '--looktype', (string) $lookType,
        '--category', $category,
        '--output', $outputFile,
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
