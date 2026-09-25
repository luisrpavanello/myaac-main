<?php
global $config;
/**
 * Automatic PagSeguro payment system gateway.
 *
 * @name      myaac-pagseguro
 * @author    Ivens Pontes <ivenscardoso@hotmail.com>
 * @author    Slawkens <slawkens@gmail.com>
 * @author    Elson <elsongabriel@hotmail.com>
 * @author    OpenTibiaBR
 * @copyright 2024 MyAAC
 * @link      https://github.com/opentibiabr/myaac
 * @version   2.0
 */
defined('MYAAC') or die('Direct access not allowed!');

// The public storefront is USD-only. PagSeguro supports BRL only, so this
// retired endpoint must not be able to start a payment in another currency.
header('Location: ' . BASE_URL . '?subtopic=donate&type=coins', true, 302);
exit;
