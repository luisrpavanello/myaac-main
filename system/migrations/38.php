<?php

// Normalize legacy FAQ visibility column.
if($db->hasTable(TABLE_PREFIX . 'faq') && !$db->hasColumn(TABLE_PREFIX . 'faq', 'hidden')) {
	$after = $db->hasColumn(TABLE_PREFIX . 'faq', 'hide') ? ' AFTER `hide`' : ' AFTER `ordering`';
	$db->query("ALTER TABLE `" . TABLE_PREFIX . "faq` ADD `hidden` TINYINT(1) NOT NULL DEFAULT 0" . $after . ";");

	if($db->hasColumn(TABLE_PREFIX . 'faq', 'hide')) {
		$db->query("UPDATE `" . TABLE_PREFIX . "faq` SET `hidden` = `hide`;");
	}
}
?>
