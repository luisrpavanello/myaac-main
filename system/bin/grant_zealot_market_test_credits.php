<?php

if (PHP_SAPI !== 'cli') {
    exit("This script can be run only from the command line.\n");
}

require_once __DIR__ . '/../../common.php';
require_once SYSTEM . 'functions.php';
require_once SYSTEM . 'init.php';

$testAccounts = ['test1', 'test2'];
$creditAmount = 50000;

foreach ($testAccounts as $name) {
    $db->beginTransaction();
    try {
        $account = $db->query('SELECT `id`, `coins_transferable` FROM `accounts` WHERE `name` = ' . $db->quote($name) . ' FOR UPDATE')->fetch();
        if (!$account) {
            throw new RuntimeException('Test account not found: ' . $name);
        }

        $balanceBefore = (int) $account['coins_transferable'];
        $db->exec('UPDATE `accounts` SET `coins_transferable` = `coins_transferable` + ' . $creditAmount . ' WHERE `id` = ' . (int) $account['id']);
        $db->exec(
            'INSERT INTO `myaac_zealot_market_ledger` (`account_id`, `auction_id`, `entry_type`, `amount`, `balance_after`, `description`, `created_at`) VALUES ('
            . (int) $account['id'] . ', NULL, \'test_credit\', ' . $creditAmount . ', ' . ($balanceBefore + $creditAmount) . ', \'Development test credit\', NOW())'
        );
        $db->commit();
        echo $name . ': +' . number_format($creditAmount) . " transferable Zealot Coins\n";
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
