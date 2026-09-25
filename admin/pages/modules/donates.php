<?php
global $db, $twig;
/**
 * Automatic PagSeguro payment system gateway.
 *
 * @name      myaac-pagseguro
 * @author    Elson <elsongabriel@hotmail.com>
 * @author    OpenTibiaBR
 * @copyright 2024 MyAAC
 * @link      https://github.com/opentibiabr/myaac
 * @version   2.0
 */

$result = [];
if ($db->hasTable('myaac_stripe_transactions')) {
    $query = $db->query("SELECT `account_id`, SUM(`amount_total`) AS `total_cents` FROM `myaac_stripe_transactions` WHERE `status` = 'paid' GROUP BY `account_id` ORDER BY `total_cents` DESC LIMIT 10;")->fetchAll();
    foreach ($query as $item) {
        if ($acc = $db->query('SELECT `id`, `name`, `email` FROM `accounts` WHERE `id` = ' . (int) $item['account_id'])->fetch()) {
            $result[$acc['id']] = [
                'name'    => $acc['name'],
                'email'   => $acc['email'],
                'players' => getPlayerByAccountId($acc['id']),
                'value'   => 'USD ' . number_format((float) $item['total_cents'] / 100, 2, '.', ',')
            ];
        }
    }
}
$twig->display('most_donates.html.twig', ['result' => $result]);
