<?php

if (PHP_SAPI !== 'cli') {
    exit("This script can be run only from the command line.\n");
}

require_once __DIR__ . '/../../common.php';
require_once SYSTEM . 'functions.php';
require_once SYSTEM . 'init.php';

$db->query(
    'CREATE TABLE IF NOT EXISTS `myaac_charbazaar` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `account_old` INT(11) NOT NULL,
        `account_new` INT(11) NOT NULL,
        `player_id` INT(11) NOT NULL,
        `price` INT(11) NOT NULL,
        `date_end` DATETIME NOT NULL,
        `date_start` DATETIME NOT NULL,
        `bid_account` INT(11) NOT NULL DEFAULT 0,
        `bid_price` INT(11) NOT NULL DEFAULT 0,
        `status` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `zealot_market_active` (`date_end`),
        KEY `zealot_market_status_end` (`status`, `date_end`),
        KEY `zealot_market_player` (`player_id`),
        KEY `zealot_market_seller` (`account_old`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;'
);

$marketColumns = [
    'starting_price' => 'INT(11) NOT NULL DEFAULT 0 AFTER `price`',
    'escrow_account_id' => 'INT(11) NOT NULL DEFAULT 0 AFTER `status`',
    'listing_fee' => 'INT(11) NOT NULL DEFAULT 0 AFTER `escrow_account_id`',
    'tax_rate' => 'TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `listing_fee`',
    'character_snapshot' => 'LONGTEXT NULL DEFAULT NULL AFTER `tax_rate`',
    'final_price' => 'INT(11) NOT NULL DEFAULT 0 AFTER `tax_rate`',
    'completed_at' => 'DATETIME NULL DEFAULT NULL AFTER `final_price`',
    'cancelled_at' => 'DATETIME NULL DEFAULT NULL AFTER `completed_at`',
];
foreach ($marketColumns as $column => $definition) {
    if (!$db->hasColumn('myaac_charbazaar', $column)) {
        $db->query('ALTER TABLE `myaac_charbazaar` ADD COLUMN `' . $column . '` ' . $definition);
    }
}

$db->query(
    'CREATE TABLE IF NOT EXISTS `myaac_charbazaar_bid` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `account_id` INT(11) NOT NULL,
        `auction_id` INT(11) NOT NULL,
        `bid` INT(11) NOT NULL,
        `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `zealot_market_bid_auction` (`auction_id`),
        KEY `zealot_market_bid_account` (`account_id`),
        KEY `zealot_market_bid_account_auction` (`account_id`, `auction_id`, `id`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;'
);

if (!$db->hasColumn('myaac_charbazaar_bid', 'is_winning')) {
    $db->query('ALTER TABLE `myaac_charbazaar_bid` ADD COLUMN `is_winning` TINYINT(1) NOT NULL DEFAULT 0 AFTER `bid`');
}

$ensureIndex = static function (string $table, string $index, string $columns) use ($db): void {
    foreach ($db->query('SHOW INDEX FROM `' . $table . '`') as $existing) {
        if (($existing['Key_name'] ?? '') === $index) {
            return;
        }
    }

    $db->query('ALTER TABLE `' . $table . '` ADD KEY `' . $index . '` (' . $columns . ')');
};

$ensureIndex('myaac_charbazaar', 'zealot_market_status_end', '`status`, `date_end`');
$ensureIndex('myaac_charbazaar', 'zealot_market_status_completed', '`status`, `completed_at`');
$ensureIndex('myaac_charbazaar_bid', 'zealot_market_bid_account_auction', '`account_id`, `auction_id`, `id`');

$db->query(
    'CREATE TABLE IF NOT EXISTS `myaac_zealot_market_ledger` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `account_id` INT(11) NOT NULL,
        `auction_id` INT(11) NULL DEFAULT NULL,
        `entry_type` VARCHAR(32) NOT NULL,
        `amount` INT(11) NOT NULL,
        `balance_after` INT(11) NOT NULL,
        `description` VARCHAR(190) NOT NULL DEFAULT \'\',
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `zealot_market_ledger_account` (`account_id`, `created_at`),
        KEY `zealot_market_ledger_auction` (`auction_id`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;'
);

$db->query(
    'CREATE TABLE IF NOT EXISTS `myaac_zealot_market_hidden_history` (
        `account_id` INT(11) NOT NULL,
        `auction_id` INT(11) NOT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`account_id`, `auction_id`),
        KEY `zealot_market_hidden_history_auction` (`auction_id`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;'
);

$db->revalidateCache();

echo "Zealot Market tables are ready.\n";
