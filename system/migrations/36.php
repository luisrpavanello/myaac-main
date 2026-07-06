<?php

// Normalize legacy forum board visibility column.
if($db->hasTable(TABLE_PREFIX . 'forum_boards') && !$db->hasColumn(TABLE_PREFIX . 'forum_boards', 'hidden')) {
	$after = $db->hasColumn(TABLE_PREFIX . 'forum_boards', 'hide') ? ' AFTER `hide`' : ' AFTER `closed`';
	$db->query("ALTER TABLE `" . TABLE_PREFIX . "forum_boards` ADD `hidden` TINYINT(1) NOT NULL DEFAULT 0" . $after . ";");

	if($db->hasColumn(TABLE_PREFIX . 'forum_boards', 'hide')) {
		$db->query("UPDATE `" . TABLE_PREFIX . "forum_boards` SET `hidden` = `hide`;");
	}
}
?>
