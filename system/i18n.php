<?php
/**
 * Lightweight public-site localization.
 *
 * MyAAC's historical locale files are used by the installer only. The Zealot
 * theme needs a request-scoped language that can be selected by a visitor, so
 * this file keeps the site language in the existing PHP session and exposes a
 * small, safe translator for templates and legacy pages.
 */

defined('MYAAC') or die('Direct access not allowed!');

function siteLanguages()
{
    return [
        'en' => ['label' => 'English', 'flag' => '🇺🇸'],
        'es' => ['label' => 'Español', 'flag' => '🇪🇸'],
        'pt' => ['label' => 'Português', 'flag' => '🇧🇷'],
    ];
}

function siteLanguage()
{
    global $config, $siteTranslations;

    static $language = null;
    if ($language !== null) {
        return $language;
    }

    $languages = siteLanguages();
    $requested = strtolower((string) ($_GET['lang'] ?? getSession('site_language') ?: $config['language'] ?? 'en'));
    // Keep compatibility with the locale name bundled with MyAAC.
    if ($requested === 'pt_br') {
        $requested = 'pt';
    }
    $language = array_key_exists($requested, $languages) ? $requested : 'en';

    if (isset($_GET['lang'])) {
        setSession('site_language', $language);
    }

    $translationFile = SYSTEM . 'locale/site/' . $language . '.php';
    $siteTranslations = is_file($translationFile) ? require $translationFile : [];
    $config['language'] = $language;

    return $language;
}

function t($key, $fallback = null, array $replace = [])
{
    global $siteTranslations;

    siteLanguage();
    $value = $siteTranslations[$key] ?? $fallback ?? $key;
    foreach ($replace as $name => $replacement) {
        $value = str_replace('{' . $name . '}', (string) $replacement, $value);
    }

    return $value;
}

function siteText($english)
{
    siteLanguage();
    return $GLOBALS['siteTranslations']['legacy'][$english] ?? $english;
}

/**
 * Format interface dates with the month abbreviations of the selected site
 * language. Game timestamps remain timestamps; only their presentation is
 * localized.
 */
function siteDate($format, $timestamp = null)
{
    $value = date($format, $timestamp ?? time());
    $months = [
        'es' => ['Jan' => 'ene', 'Feb' => 'feb', 'Mar' => 'mar', 'Apr' => 'abr', 'May' => 'may', 'Jun' => 'jun', 'Jul' => 'jul', 'Aug' => 'ago', 'Sep' => 'sept', 'Oct' => 'oct', 'Nov' => 'nov', 'Dec' => 'dic'],
        'pt' => ['Jan' => 'jan', 'Feb' => 'fev', 'Mar' => 'mar', 'Apr' => 'abr', 'May' => 'mai', 'Jun' => 'jun', 'Jul' => 'jul', 'Aug' => 'ago', 'Sep' => 'set', 'Oct' => 'out', 'Nov' => 'nov', 'Dec' => 'dez'],
    ];

    return isset($months[siteLanguage()]) ? strtr($value, $months[siteLanguage()]) : $value;
}

function siteLanguageUrl($language)
{
    $languages = siteLanguages();
    if (!isset($languages[$language])) {
        $language = 'en';
    }

    return BASE_URL . 'tools/language.php?' . http_build_query([
        'lang' => $language,
        'return' => $_SERVER['REQUEST_URI'] ?? BASE_URL,
    ], '', '&', PHP_QUERY_RFC3986);
}

/**
 * Translate stable interface phrases emitted by legacy MyAAC pages. Dynamic
 * game data is deliberately excluded: player, guild, house and news names
 * remain exactly as their author entered them.
 */
function translateSiteHtml($html)
{
    if (siteLanguage() === 'en' || !is_string($html) || $html === '') {
        return $html;
    }

    $replace = $GLOBALS['siteTranslations']['legacy'] ?? [];
    if (!$replace) {
        return $html;
    }

    // Replace complete visible text nodes only. A global string replacement
    // would turn "Highscores Filter" into a mixed-language label and could
    // alter values inside JavaScript, URLs, or player-authored copy.
    $html = preg_replace_callback('/(?<=>)([^<>]+)(?=<)/u', static function ($match) use ($replace) {
        $leading = strlen($match[1]) - strlen(ltrim($match[1]));
        $trailing = strlen($match[1]) - strlen(rtrim($match[1]));
        $text = trim($match[1]);
        if (!isset($replace[$text])) {
            return $match[1];
        }
        return substr($match[1], 0, $leading) . $replace[$text] . ($trailing ? substr($match[1], -$trailing) : '');
    }, $html);

    // Some controls expose their visible label through an attribute instead
    // of a text node (buttons, mobile table labels and accessible controls).
    // Translate only a complete, known interface phrase; field names, URLs
    // and dynamic player content are deliberately outside this allow-list.
    return preg_replace_callback('/<[^>]+>/u', static function ($tag) use ($replace) {
        return preg_replace_callback('/\\b(value|placeholder|title|aria-label|data-label|alt)=([' . "\"'" . '])(.*?)\\2/iu', static function ($attribute) use ($replace) {
            $text = html_entity_decode($attribute[3], ENT_QUOTES, 'UTF-8');
            if (!isset($replace[$text])) {
                return $attribute[0];
            }
            return $attribute[1] . '=' . $attribute[2] . htmlspecialchars($replace[$text], ENT_QUOTES, 'UTF-8') . $attribute[2];
        }, $tag[0]);
    }, $html);
}
