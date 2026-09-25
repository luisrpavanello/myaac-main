<?php
/** Persist a public-site language choice, then return to the originating page. */

require_once __DIR__ . '/../common.php';
require BASE . 'config.php';
if (is_file(BASE . 'config.local.php')) {
    require BASE . 'config.local.php';
}

$language = strtolower((string) ($_GET['lang'] ?? 'en'));
if ($language === 'pt_br') {
    $language = 'pt';
}
if (!in_array($language, ['en', 'es', 'pt'], true)) {
    $language = 'en';
}

$_SESSION[($config['session_prefix'] ?? '') . 'site_language'] = $language;

$returnTo = (string) ($_GET['return'] ?? BASE_URL);
$target = parse_url($returnTo);
if ($target === false || isset($target['scheme']) || isset($target['host']) || substr($returnTo, 0, 1) !== '/') {
    $returnTo = BASE_URL;
}

header('Location: ' . $returnTo, true, 303);
exit;
