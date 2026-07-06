<?php
/**
 * Create guild
 *
 * @package   MyAAC
 * @author    Gesior <jerzyskalski@wp.pl>
 * @author    Slawkens <slawkens@gmail.com>
 * @author    OpenTibiaBR
 * @copyright 2023 MyAAC
 * @link      https://github.com/opentibiabr/myaac
 */
defined('MYAAC') or die('Direct access not allowed!');

$guild_name = isset($_REQUEST['guild']) ? urldecode($_REQUEST['guild']) : NULL;
$name = isset($_REQUEST['name']) ? stripslashes($_REQUEST['name']) : NULL;
$todo = isset($_REQUEST['todo']) ? $_REQUEST['todo'] : NULL;
if (!$logged) {
    $guild_errors[] = 'You are not logged in. You can\'t create guild.';
}

$array_of_player_nig = array();
if (empty($guild_errors)) {
    $account_players = $account_logged->getPlayersList(false);
    foreach ($account_players as $player) {
        $player_rank = $player->getRank();
        if (!$player_rank->isLoaded()) {
            if ($player->getLevel() >= $config['guild_need_level']) {
                if (!$config['guild_need_premium'] || $account_logged->isPremium()) {
                    $array_of_player_nig[] = $player->getName();
                }
            }
        }
    }
}

if (empty($todo)) {
    if (count($array_of_player_nig) == 0) {
        $guild_errors[] = 'On your account all characters are in guilds, have too low level to create new guild' . ($config['guild_need_premium'] ? ' or you don\' have a premium account' : '') . '.';
    }
}

if ($todo == 'save') {
    if (!Validator::guildName($guild_name)) {
        $guild_errors[] = Validator::getLastError();
        $guild_name = '';
    }

    if (empty($guild_errors)) {
        $name = trim((string) $name);
        $player = new OTS_Player();
        $player->find($name);
        if (!$player->isLoaded()) {
            $guild_errors[] = 'Character <b>' . $name . '</b> doesn\'t exist.';
        }
    }


    if (empty($guild_errors)) {
        $guild = new OTS_Guild();
        $guild->find($guild_name);
        if ($guild->isLoaded()) {
            $guild_errors[] = 'Guild <b>' . $guild_name . '</b> already exist. Select other name.';
        }
    }

    if (empty($guild_errors) && $player->isDeleted()) {
        $guild_errors[] = "Character <b>$name</b> has been deleted.";
    }

    if (empty($guild_errors)) {
        $ownerid = $db->hasColumn('guilds', 'owner_id') ? 'owner_id' : 'ownerid';
        $ownerGuild = $db->query('SELECT `id`, `name` FROM `guilds` WHERE `' . $ownerid . '` = ' . (int) $player->getId() . ' LIMIT 1')->fetch();
        if ($ownerGuild !== false && isset($ownerGuild['id'])) {
            $guild_errors[] = 'Character <b>' . $name . '</b> already owns guild <b>' . $ownerGuild['name'] . '</b>.';
        }
    }

    if (empty($guild_errors)) {
        $bad_char = true;
        foreach ($array_of_player_nig as $nick_from_list) {
            if ($nick_from_list == $player->getName()) {
                $bad_char = false;
            }
        }
        if ($bad_char) {
            $guild_errors[] = 'Character <b>' . $name . '</b> isn\'t on your account or is already in guild.';
        }
    }

    if (empty($guild_errors)) {
        if ($player->getLevel() < $config['guild_need_level']) {
            $guild_errors[] = 'Character <b>' . $name . '</b> has too low level. To create guild you need character with level <b>' . $config['guild_need_level'] . '</b>.';
        }
        if ($config['guild_need_premium'] && !$account_logged->isPremium()) {
            $guild_errors[] = 'Character <b>' . $name . '</b> is on FREE account. To create guild you need PREMIUM account.';
        }
    }
}

if (!empty($guild_errors)) {
    $twig->display('error_box.html.twig', array('errors' => $guild_errors));
    unset($todo);
}

if (isset($todo) && $todo == 'save') {
    try {
        $db->exec('START TRANSACTION');

        $ownerid = $db->hasColumn('guilds', 'owner_id') ? 'owner_id' : 'ownerid';
        $creationdata = 'creationdata';
        if ($db->hasColumn('guilds', 'creationdate')) {
            $creationdata = 'creationdate';
        } else if ($db->hasColumn('guilds', 'creation_time')) {
            $creationdata = 'creation_time';
        }

        $columns = array('name', $ownerid, $creationdata);
        $values = array($db->quote($guild_name), (int) $player->getId(), time());

        if ($db->hasColumn('guilds', 'description')) {
            $columns[] = 'description';
            $values[] = $db->quote('New guild. Leader must edit this text :)');
        }

        if ($db->hasColumn('guilds', 'logo_name')) {
            $columns[] = 'logo_name';
            $values[] = $db->quote('default.gif');
        }

        $db->exec('INSERT INTO `guilds` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $values) . ')');
        $guild_id = (int) $db->lastInsertId();

        $leaderRank = $db->query('SELECT `id` FROM `guild_ranks` WHERE `guild_id` = ' . $guild_id . ' AND `level` = 3 ORDER BY `id` ASC LIMIT 1')->fetch();
        if ($leaderRank === false || !isset($leaderRank['id'])) {
            $db->exec('INSERT INTO `guild_ranks` (`name`, `level`, `guild_id`) VALUES (' . $db->quote('The Leader') . ', 3, ' . $guild_id . ')');
            $leaderRank = array('id' => (int) $db->lastInsertId());
            $db->exec('INSERT INTO `guild_ranks` (`name`, `level`, `guild_id`) VALUES (' . $db->quote('Vice-Leader') . ', 2, ' . $guild_id . ')');
            $db->exec('INSERT INTO `guild_ranks` (`name`, `level`, `guild_id`) VALUES (' . $db->quote('Member') . ', 1, ' . $guild_id . ')');
        }

        if ($db->hasTable('guild_membership')) {
            $db->exec(
                'INSERT INTO `guild_membership` (`player_id`, `guild_id`, `rank_id`, `nick`) VALUES (' .
                (int) $player->getId() . ', ' . $guild_id . ', ' . (int) $leaderRank['id'] . ', ' . $db->quote('') . ')' .
                ' ON DUPLICATE KEY UPDATE `guild_id` = VALUES(`guild_id`), `rank_id` = VALUES(`rank_id`), `nick` = VALUES(`nick`)'
            );
        } else if ($db->hasTable('guild_members')) {
            $db->exec(
                'INSERT INTO `guild_members` (`player_id`, `rank_id`, `nick`) VALUES (' .
                (int) $player->getId() . ', ' . (int) $leaderRank['id'] . ', ' . $db->quote('') . ')' .
                ' ON DUPLICATE KEY UPDATE `rank_id` = VALUES(`rank_id`), `nick` = VALUES(`nick`)'
            );
        } else if ($db->hasColumn('players', 'rank_id')) {
            $player->setRankId((int) $leaderRank['id'], $guild_id);
        }

        $db->exec('COMMIT');

        $twig->display('guilds.create.success.html.twig', array(
            'guild_name' => $guild_name,
            'leader_name' => $player->getName()
        ));
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        error_log('Guild creation failed: ' . $e->getMessage());
        $twig->display('error_box.html.twig', array('errors' => array('Could not create guild. Please try again or contact support.')));
    }

    /*$db->exec('INSERT INTO `guild_ranks` (`id`, `guild_id`, `name`, `level`) VALUES (null, '.$new_guild->getId().', "the Leader", 3)');
    $db->exec('INSERT INTO `guild_ranks` (`id`, `guild_id`, `name`, `level`) VALUES (null, '.$new_guild->getId().', "a Vice-Leader", 2)');
    $db->exec('INSERT INTO `guild_ranks` (`id`, `guild_id`, `name`, `level`) VALUES (null, '.$new_guild->getId().', "a Member", 1)');*/
} else {
    sort($array_of_player_nig);
    $twig->display('guilds.create.html.twig', array(
        'players' => $array_of_player_nig
    ));
}
