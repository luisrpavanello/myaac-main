<?php

if(!$db->hasTable(TABLE_PREFIX . 'gallery')) {
	$db->query("CREATE TABLE `" . TABLE_PREFIX . "gallery` (
		`id` INT(11) NOT NULL AUTO_INCREMENT,
		`comment` VARCHAR(255) NOT NULL DEFAULT '',
		`image` VARCHAR(255) NOT NULL,
		`thumb` VARCHAR(255) NOT NULL,
		`author` VARCHAR(50) NOT NULL DEFAULT '',
		`ordering` INT(11) NOT NULL DEFAULT 0,
		`hidden` TINYINT(1) NOT NULL DEFAULT 0,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;");

	$db->query("INSERT INTO `" . TABLE_PREFIX . "gallery` (`ordering`, `comment`, `image`, `thumb`, `author`) VALUES (1, 'Demon', 'images/gallery/demon.jpg', 'images/gallery/demon_thumb.gif', 'MyAAC');");
}
?>
