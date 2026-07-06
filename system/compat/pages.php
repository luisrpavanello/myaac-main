<?php
/**
 * Compat pages (backward support for Gesior AAC)
 *
 * @package   MyAAC
 * @author    Slawkens <slawkens@gmail.com>
 * @author    OpenTibiaBR
 * @copyright 2023 MyAAC
 * @link      https://github.com/opentibiabr/myaac
 */
defined('MYAAC') or die('Direct access not allowed!');
switch ($page) {
    case 'adminpanel':
        header('Location: ' . ADMIN_URL);
        die;

    case 'archive':
        $page = 'newsarchive';
        break;

    case 'change-log':
        $page = 'changelog';
        break;

    case 'downloads':
        $page = 'downloadclient';
        break;

    case 'whoisonline':
        $page = 'online';
        break;

    case 'latestnews':
        $page = 'news';
        break;

    case 'tibiarules':
        $page = 'rules';
        break;

    case 'killstatistics':
    case 'last-kills':
        $page = 'lastkills';
        break;

    case 'monsters':
        $page = 'creatures';
        break;

    case 'ots-info':
    case 'server-info':
        $page = 'serverinfo';
        break;

    case 'exp-table':
        $page = 'experiencetable';
        break;

    case 'exp-stages':
        $page = 'expstages';
        break;

    case 'buypoints':
        $page = 'points';
        break;

    case 'shopsystem':
        $page = 'gifts';
        break;

    default:
        break;
}
?>
