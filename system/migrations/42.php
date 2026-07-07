<?php

if(!$db->hasTable(TABLE_PREFIX . 'payment_requests')) {
	$db->query("CREATE TABLE `" . TABLE_PREFIX . "payment_requests` (
		`id` INT(11) NOT NULL AUTO_INCREMENT,
		`reference` VARCHAR(40) NOT NULL,
		`account_id` INT(11) UNSIGNED NOT NULL,
		`product_type` VARCHAR(20) NOT NULL,
		`package_key` VARCHAR(80) NOT NULL,
		`package_label` VARCHAR(120) NOT NULL,
		`amount` DECIMAL(10,2) NOT NULL,
		`currency` VARCHAR(10) NOT NULL,
		`payment_method` VARCHAR(30) NOT NULL,
		`payment_network` VARCHAR(40) DEFAULT NULL,
		`transaction_id` VARCHAR(190) DEFAULT NULL,
		`payer_note` TEXT DEFAULT NULL,
		`status` VARCHAR(30) NOT NULL DEFAULT 'manual_review',
		`created_at` DATETIME NOT NULL,
		`updated_at` DATETIME DEFAULT NULL,
		PRIMARY KEY (`id`),
		UNIQUE KEY `reference` (`reference`),
		KEY `account_id` (`account_id`),
		KEY `status` (`status`),
		KEY `product_type` (`product_type`)
	) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;");
}
?>
