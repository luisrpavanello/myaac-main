<?php

/**
 * Official OTClient object sprite cache for website item icons.
 *
 * The game database stores an item's client appearance id. Instead of relying
 * on a partial legacy GIF catalogue, this exporter reads the same OTClient
 * assets used by the game and stores a transparent PNG under images/items.
 */
class OtClientItemSprites
{
    private static array $attempted = [];

    public static function ensure(int $itemId, bool $force = false): ?string
    {
        if ($itemId < 1 || $itemId > 65535) {
            return null;
        }

        $relativePath = 'images/items/' . $itemId . '.png';
        $outputPath = BASE . $relativePath;
        if (!$force && is_file($outputPath)) {
            return $relativePath;
        }

        if (!$force && isset(self::$attempted[$itemId])) {
            return is_file($outputPath) ? $relativePath : null;
        }
        self::$attempted[$itemId] = true;

        $assetsPath = self::assetsPath();
        $exporter = SYSTEM . 'bin/export_otclient_sprite.py';
        if (!$assetsPath || !is_file($exporter)) {
            return null;
        }

        $directory = dirname($outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            error_log('OTClient item sprite cache directory could not be created.');
            return null;
        }

        $temporaryPath = $outputPath . '.tmp-' . getmypid();
        $arguments = [
            'python3', $exporter, '--assets', $assetsPath, '--looktype', (string) $itemId,
            '--category', 'object', '--output', $temporaryPath, '--direction', '0',
        ];
        $command = implode(' ', array_map('escapeshellarg', $arguments));
        exec($command . ' 2>&1', $ignoredOutput, $status);

        if ($status === 0 && is_file($temporaryPath) && filesize($temporaryPath) > 0) {
            if (!@rename($temporaryPath, $outputPath) && !is_file($outputPath)) {
                @unlink($temporaryPath);
                return null;
            }
            return $relativePath;
        }

        @unlink($temporaryPath);
        return is_file($outputPath) ? $relativePath : null;
    }

    private static function assetsPath(): ?string
    {
        $configuredPath = getenv('ZEALOT_OTCLIENT_ASSETS');
        if ($configuredPath && is_file(rtrim($configuredPath, '/') . '/catalog-content.json')) {
            return rtrim($configuredPath, '/');
        }

        $versions = glob(dirname(BASE) . '/otclient/data/things/*', GLOB_ONLYDIR) ?: [];
        usort($versions, static fn($left, $right) => strnatcasecmp(basename($right), basename($left)));
        foreach ($versions as $version) {
            if (is_file($version . '/catalog-content.json')) {
                return $version;
            }
        }

        return null;
    }
}
