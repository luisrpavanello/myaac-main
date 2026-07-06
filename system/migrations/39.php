<?php

// PagSeguro/shop tables used by the local Shop pages.
if(!$db->hasTable('pagseguro_transactions')) {
	$db->query("CREATE TABLE `pagseguro_transactions` (
		`id` INT(11) NOT NULL AUTO_INCREMENT,
		`transaction_code` VARCHAR(50) NOT NULL,
		`account_id` INT(11) UNSIGNED NOT NULL,
		`payment_method` VARCHAR(50) NOT NULL,
		`payment_status` VARCHAR(50) NOT NULL,
		`code` VARCHAR(10) NOT NULL,
		`coins_amount` INT(11) NOT NULL,
		`bought` INT(11) DEFAULT NULL,
		`delivered` CHAR(1) NOT NULL DEFAULT '0',
		`in_double` CHAR(1) NOT NULL DEFAULT '0',
		`request` LONGTEXT DEFAULT NULL,
		`created_at` DATETIME NOT NULL,
		`updated_at` DATETIME DEFAULT NULL,
		PRIMARY KEY (`id`),
		UNIQUE KEY `transaction_code` (`transaction_code`, `payment_status`),
		KEY `payment_method` (`payment_method`),
		KEY `payment_status` (`payment_status`),
		KEY `coins_amount` (`coins_amount`),
		KEY `delivered` (`delivered`),
		CONSTRAINT `pagseguro_transactions_account_fk`
			FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;");
}

if(!$db->hasTable(TABLE_PREFIX . 'send_items')) {
	$db->query("CREATE TABLE `" . TABLE_PREFIX . "send_items` (
		`id` INT(11) NOT NULL AUTO_INCREMENT,
		`transaction_code` VARCHAR(50) NOT NULL,
		`item_id` VARCHAR(20) NOT NULL,
		`item_name` VARCHAR(50) NOT NULL,
		`item_count` INT(11) UNSIGNED NOT NULL DEFAULT 1,
		`account_id` INT(11) UNSIGNED NOT NULL,
		`payment_method` VARCHAR(50) NOT NULL,
		`payment_status` VARCHAR(50) NOT NULL,
		`status` CHAR(1) NOT NULL DEFAULT '0' COMMENT '0 = pending | 1 = approved | 2 = delivered | 3 = canceled',
		`request` LONGTEXT DEFAULT NULL,
		`created_at` DATETIME NOT NULL,
		`updated_at` DATETIME DEFAULT NULL,
		PRIMARY KEY (`id`),
		UNIQUE KEY `transaction_code` (`transaction_code`, `payment_status`),
		KEY `status` (`status`),
		KEY `payment_method` (`payment_method`),
		KEY `payment_status` (`payment_status`),
		CONSTRAINT `" . TABLE_PREFIX . "send_items_account_fk`
			FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;");
}
?>
