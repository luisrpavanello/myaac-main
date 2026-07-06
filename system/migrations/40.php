<?php

// Normalize legacy changelog visibility column.
if($db->hasTable(TABLE_PREFIX . 'changelog') && !$db->hasColumn(TABLE_PREFIX . 'changelog', 'hidden')) {
	$after = $db->hasColumn(TABLE_PREFIX . 'changelog', 'hide') ? ' AFTER `hide`' : ' AFTER `player_id`';
	$db->query("ALTER TABLE `" . TABLE_PREFIX . "changelog` ADD `hidden` TINYINT(1) NOT NULL DEFAULT 0" . $after . ";");

	if($db->hasColumn(TABLE_PREFIX . 'changelog', 'hide')) {
		$db->query("UPDATE `" . TABLE_PREFIX . "changelog` SET `hidden` = `hide`;");
	}
}
?>
