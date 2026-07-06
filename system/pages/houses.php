<?php
/**
 * Houses
 *
 * @package   MyAAC
 * @author    Gesior <jerzyskalski@wp.pl>
 * @author    Slawkens <slawkens@gmail.com>
 * @author    whiteblXK
 * @author    OpenTibiaBR
 * @copyright 2023 MyAAC
 * @link      https://github.com/opentibiabr/myaac
 */
defined('MYAAC') or die('Direct access not allowed!');
$title = 'Houses';

$errors = array();
$messages = array();
if(!$db->hasColumn('houses', 'name')) {
    $errors[] = 'Houses list is not available on this server.';

    $twig->display('houses.html.twig', array(
        'errors' => $errors
    ));
	return;
}

$rentType = trim(strtolower($config['lua']['houseRentPeriod']));
if($rentType != 'yearly' && $rentType != 'monthly' && $rentType != 'weekly' && $rentType != 'daily')
    $rentType = 'never';

$hasHouseAuctions = $db->hasColumn('houses', 'bidder') && $db->hasColumn('houses', 'bidder_name') && $db->hasColumn('houses', 'highest_bid') && $db->hasColumn('houses', 'internal_bid') && $db->hasColumn('houses', 'bid_end_date') && $db->hasColumn('houses', 'state') && $db->hasColumn('accounts', 'house_bid_id') && $db->hasColumn('players', 'balance');
$auctionDays = isset($config['lua']['daysToCloseBid']) ? (int)$config['lua']['daysToCloseBid'] : 7;
if($auctionDays <= 0) {
    $auctionDays = 7;
}

$state = '';
$order = '';
$type = '';

if(isset($_GET['page']) && $_GET['page'] == 'view' && isset($_REQUEST['house']))
{
    $selectedBidderId = isset($_POST['bidder']) && Validator::number($_POST['bidder']) ? (int)$_POST['bidder'] : 0;
    $postedBidValue = isset($_POST['bid']) && Validator::number($_POST['bid']) ? (int)$_POST['bid'] : null;
    $beds = array("", "one", "two", "three", "fourth", "fifth");
    $houseName = $_REQUEST['house'];
    $houseId = (Validator::number($_REQUEST['house']) ? $_REQUEST['house'] : -1);
    $selectHouse = $db->query('SELECT * FROM ' . $db->tableName('houses') . ' WHERE ' . $db->fieldName('name') . ' LIKE ' . $db->quote($houseName) . ' OR `id` = ' . $db->quote($houseId));

    $house = array();
    if($selectHouse->rowCount() > 0)
    {
        $house = $selectHouse->fetch();
        $houseId = $house['id'];

        $title = $house['name'] . ' - ' . $title;

        $accountCharacters = array();
        if($logged && $hasHouseAuctions) {
            $characters = $db->query('SELECT `id`, `name`, `vocation`, `balance` FROM `players` WHERE `account_id` = ' . (int)$account_logged->getId() . ' ORDER BY `name` ASC')->fetchAll();
            foreach($characters as $character) {
                $bidMinimum = max(1, (int)$house['highest_bid'] + 1);
                $requiredBalance = (int)$house['rent'] + $bidMinimum;
                $accountCharacters[] = array(
                    'id' => (int)$character['id'],
                    'name' => $character['name'],
                    'vocation' => (int)$character['vocation'],
                    'balance' => (int)$character['balance'],
                    'can_bid_minimum' => (int)$character['vocation'] > 0 && (int)$character['balance'] >= $requiredBalance,
                    'required_balance' => $requiredBalance
                );
            }
        }

        if(isset($_POST['action']) && $_POST['action'] === 'bid_house') {
            if(!$hasHouseAuctions) {
                $errors[] = 'House auctions are not available on this server.';
            }
            else if(!$logged) {
                $errors[] = 'You need to login before bidding on a house.';
            }
            else if((int)$house['owner'] > 0 || (int)$house['state'] !== 0) {
                $errors[] = 'This house is not available for auction.';
            }
            else if(!$account_logged->isPremium()) {
                $errors[] = 'Only premium accounts can bid on houses.';
            }
            else {
                $bidderId = $selectedBidderId;
                $bidValue = $postedBidValue !== null ? $postedBidValue : 0;
                $character = null;
                foreach($accountCharacters as $accountCharacter) {
                    if($accountCharacter['id'] === $bidderId) {
                        $character = $accountCharacter;
                        break;
                    }
                }

                if($character === null) {
                    $errors[] = 'Please select one of your characters.';
                }
                else if($character['vocation'] <= 0) {
                    $errors[] = 'Rookgaard characters cannot bid on houses.';
                }
                else if($bidValue <= 0) {
                    $errors[] = 'Please enter a valid bid value.';
                }
                else if((int)$account_logged->getCustomField('house_bid_id') > 0 && (int)$account_logged->getCustomField('house_bid_id') !== (int)$house['id']) {
                    $errors[] = 'Your account already has an active house bid.';
                }
                else {
                    $currentHighestBid = (int)$house['highest_bid'];
                    $currentInternalBid = (int)$house['internal_bid'];
                    $currentBidder = (int)$house['bidder'];
                    $reserve = (int)$house['rent'] + $bidValue;

                    if($bidValue <= $currentHighestBid) {
                        $errors[] = 'Your bid must be higher than the current highest bid.';
                    }
                    else if($currentBidder === $character['id'] && $bidValue <= $currentInternalBid) {
                        $errors[] = 'Your new bid must be higher than your current maximum bid.';
                    }
                    else if($character['balance'] < $reserve && $currentBidder !== $character['id']) {
                        $errors[] = 'The selected character needs ' . $reserve . ' gold in the bank for this bid and rent, but only has ' . $character['balance'] . ' gold.';
                    }
                    else if($currentBidder === $character['id'] && $character['balance'] < ($bidValue - $currentInternalBid)) {
                        $neededIncrease = $bidValue - $currentInternalBid;
                        $errors[] = 'The selected character needs ' . $neededIncrease . ' gold in the bank to increase this bid, but only has ' . $character['balance'] . ' gold.';
                    }
                    else {
                        $db->beginTransaction();
                        try {
                            $lockedHouse = $db->query('SELECT * FROM `houses` WHERE `id` = ' . (int)$house['id'] . ' FOR UPDATE')->fetch();
                            $lockedCharacter = $db->query('SELECT `id`, `name`, `account_id`, `vocation`, `balance` FROM `players` WHERE `id` = ' . $bidderId . ' AND `account_id` = ' . (int)$account_logged->getId() . ' FOR UPDATE')->fetch();

                            if(!$lockedHouse || !$lockedCharacter || (int)$lockedHouse['owner'] > 0 || (int)$lockedHouse['state'] !== 0) {
                                throw new RuntimeException('This house is not available for auction.');
                            }

                            $newHighestBid = (int)$lockedHouse['highest_bid'];
                            $newInternalBid = (int)$lockedHouse['internal_bid'];
                            $newBidder = (int)$lockedHouse['bidder'];
                            $newBidderName = $lockedHouse['bidder_name'];
                            $bidEndDate = (int)$lockedHouse['bid_end_date'];
                            $balanceChange = 0;

                            if($newBidder === 0) {
                                $balanceChange = (int)$lockedHouse['rent'] + $bidValue;
                                $newHighestBid = 0;
                                $newInternalBid = $bidValue;
                                $newBidder = (int)$lockedCharacter['id'];
                                $newBidderName = $lockedCharacter['name'];
                                $bidEndDate = time() + ($auctionDays * 86400);
                            }
                            else if($newBidder === (int)$lockedCharacter['id']) {
                                if($bidValue <= $newInternalBid) {
                                    throw new RuntimeException('Your new bid must be higher than your current maximum bid.');
                                }

                                $balanceChange = $bidValue - $newInternalBid;
                                $newInternalBid = $bidValue;
                            }
                            else if($bidValue <= $newInternalBid) {
                                $newHighestBid = $bidValue;
                                $messages[] = 'Your bid was registered, but another character still has the highest bid.';
                            }
                            else {
                                $balanceChange = (int)$lockedHouse['rent'] + $bidValue;
                                $refund = (int)$lockedHouse['rent'] + $newInternalBid;
                                $db->exec('UPDATE `players` SET `balance` = `balance` + ' . $refund . ' WHERE `id` = ' . $newBidder);
                                $oldBidder = $db->query('SELECT `account_id` FROM `players` WHERE `id` = ' . $newBidder)->fetch();
                                if($oldBidder) {
                                    $db->exec('UPDATE `accounts` SET `house_bid_id` = 0 WHERE `id` = ' . (int)$oldBidder['account_id']);
                                }

                                $newHighestBid = $newInternalBid + 1;
                                $newInternalBid = $bidValue;
                                $newBidder = (int)$lockedCharacter['id'];
                                $newBidderName = $lockedCharacter['name'];
                            }

                            if($balanceChange > 0) {
                                if((int)$lockedCharacter['balance'] < $balanceChange) {
                                    throw new RuntimeException('The selected character does not have enough money in the bank.');
                                }

                                $db->exec('UPDATE `players` SET `balance` = `balance` - ' . $balanceChange . ' WHERE `id` = ' . (int)$lockedCharacter['id']);
                            }

                            $db->exec('UPDATE `houses` SET `bidder` = ' . $newBidder . ', `bidder_name` = ' . $db->quote($newBidderName) . ', `highest_bid` = ' . $newHighestBid . ', `internal_bid` = ' . $newInternalBid . ', `bid_end_date` = ' . $bidEndDate . ', `state` = 0 WHERE `id` = ' . (int)$lockedHouse['id']);
                            if($newBidder === (int)$lockedCharacter['id']) {
                                $db->exec('UPDATE `accounts` SET `house_bid_id` = ' . (int)$lockedHouse['id'] . ' WHERE `id` = ' . (int)$account_logged->getId());
                                $messages[] = 'Your house bid has been submitted successfully.';
                            }

                            $db->commit();

                            $house = $db->query('SELECT * FROM `houses` WHERE `id` = ' . (int)$house['id'])->fetch();
                            $characters = $db->query('SELECT `id`, `name`, `vocation`, `balance` FROM `players` WHERE `account_id` = ' . (int)$account_logged->getId() . ' ORDER BY `name` ASC')->fetchAll();
                            $accountCharacters = array();
                            foreach($characters as $character) {
                                $bidMinimum = max(1, (int)$house['highest_bid'] + 1);
                                $requiredBalance = (int)$house['rent'] + $bidMinimum;
                                $accountCharacters[] = array(
                                    'id' => (int)$character['id'],
                                    'name' => $character['name'],
                                    'vocation' => (int)$character['vocation'],
                                    'balance' => (int)$character['balance'],
                                    'can_bid_minimum' => (int)$character['vocation'] > 0 && (int)$character['balance'] >= $requiredBalance,
                                    'required_balance' => $requiredBalance
                                );
                            }
                        }
                        catch(Exception $e) {
                            $db->rollBack();
                            $errors[] = $e->getMessage() ?: 'The house bid could not be completed.';
                        }
                    }
                }
            }
        }

        $imgPath = 'images/houses/' . $houseId . '.gif';
        if(!file_exists($imgPath)) {
            $imgPath = 'images/houses/default.jpg';
        }

        $bedsMessage = null;
        $houseBeds = $house['beds'];
        if($houseBeds > 0)
            $bedsMessage = 'House have ' . (isset($beds[$houseBeds]) ? $beds[$houseBeds] : $houseBeds) . ' bed' . ($houseBeds > 1 ? 's' : '');
        else
            $bedsMessage = 'This house dont have any beds';

        $houseOwner = $house['owner'];
        if($houseOwner > 0)
        {
            $guild = NULL;
            $owner = null;
            if(isset($house['guild']) && $house['guild'] == 1)
            {
                $guild = new OTS_Guild();
                $guild->load($houseOwner);
                $owner = getGuildLink($guild->getName());
            }
            else
                $owner = getCreatureName($houseOwner);

            if($rentType != 'never' && $house['paid'] > 0)
            {
                $who = '';
                if($guild)
                    $who = $guild->getName();
                else
                {
                    $player = new OTS_Player();
                    $player->load($houseOwner);
                    if($player->isLoaded())
                    {
                        $sexs = array('She', 'He');
                        $who = $sexs[$player->getSex()];
                    }
                }
                $owner .= ' ' . $who . ' has paid the rent until ' . date("M d Y, H:i:s", $house['paid']) . ' CEST.';
            }
        }
    }
    else
        $errors[] =  'House with name ' . $houseName . ' does not exists.';

    $twig->display('houses.view.html.twig', array(
        'errors' => $errors,
        'messages' => $messages,
        'imgPath' => isset($imgPath) ? $imgPath : null,
        'houseName' => isset($house['name']) ? $house['name'] : null,
        'houseId' => isset($house['id']) ? $house['id'] : null,
        'bedsMessage' => isset($bedsMessage) ? $bedsMessage : null,
        'houseSize' => isset($house['size']) ? $house['size'] : null,
        'houseRent' => isset($house['rent']) ? $house['rent'] : null,
        'houseOwner' => isset($house['owner']) ? (int)$house['owner'] : null,
        'houseState' => isset($house['state']) ? (int)$house['state'] : null,
        'auctionEnabled' => $hasHouseAuctions,
        'accountCharacters' => isset($accountCharacters) ? $accountCharacters : array(),
        'currentBidder' => isset($house['bidder_name']) ? $house['bidder_name'] : null,
        'currentHighestBid' => isset($house['highest_bid']) ? (int)$house['highest_bid'] : 0,
        'currentInternalBid' => isset($house['internal_bid']) ? (int)$house['internal_bid'] : 0,
        'bidMinimum' => isset($house['highest_bid']) ? max(1, (int)$house['highest_bid'] + 1) : 1,
        'bidRequired' => isset($house['rent'], $house['highest_bid']) ? (int)$house['rent'] + max(1, (int)$house['highest_bid'] + 1) : 0,
        'selectedBidderId' => $selectedBidderId,
        'postedBidValue' => $postedBidValue,
        'bidEndDate' => isset($house['bid_end_date']) ? (int)$house['bid_end_date'] : 0,
        'owner' => isset($owner) ? $owner : null,
        'rentType' => isset($rentType) ? $rentType : null
    ));

    return;
}

$cleanOldHouse = null;
if(isset($config['lua']['houseCleanOld'])) {
    $cleanOldHouse = (int)(eval('return ' . $config['lua']['houseCleanOld'] . ';') / (24 * 60 * 60));
}

$housesSearch = false;
if(isset($_POST['town']) && isset($_POST['state']) && isset($_POST['order']) && (isset($_POST['type']) || !$db->hasColumn('houses', 'guild')))
{
    $townName = $config['towns'][$_POST['town']];
    $order = $_POST['order'];
    $orderby = '`name`';
    if(!empty($order))
    {
        if($order == 'size')
            $orderby = '`size`';
        else if($order == 'rent')
            $orderby = '`rent`';
    }

    $town = 'town';
    if($db->hasColumn('houses', 'town_id'))
        $town = 'town_id';
    else if($db->hasColumn('houses', 'townid'))
        $town = 'townid';

    $whereby = '`' . $town . '` = ' .(int)$_POST['town'];
    $state = $_POST['state'];
    if(!empty($state))
        $whereby .= ' AND `owner` ' . ($state == 'free' ? '' : '!'). '= 0';

    $type = isset($_POST['type']) ? $_POST['type'] : NULL;
    if($type == 'guildhalls' && !$db->hasColumn('houses', 'guild'))
        $type = 'all';

	if (!empty($type) && $type != 'all')
	{
		$guildColumn = '';
		if ($db->hasColumn('houses', 'guild')) {
			$guildColumn = 'guild';
		}
		else if ($db->hasColumn('houses', 'guildid')) {
			$guildColumn = 'guildid';
		}

		if($guildColumn !== '') {
			$whereby .= ' AND `' . $guildColumn . '` ' . ($type == 'guildhalls' ? '!' : '') . '= 0';
		}
	}

    $houses_info = $db->query('SELECT * FROM `houses` WHERE ' . $whereby. ' ORDER BY ' . $orderby);

    $players_info = $db->query("SELECT `houses`.`id` AS `houseid` , `players`.`name` AS `ownername` FROM `houses` , `players` , `accounts` WHERE `players`.`id` = `houses`.`owner` AND `accounts`.`id` = `players`.`account_id`");
    $players = array();
    foreach($players_info->fetchAll() as $player)
        $players[$player['houseid']] = array('name' => $player['ownername']);

    $houses = array();
    foreach($houses_info->fetchAll() as $house)
    {
        $owner = isset($players[$house['id']]) ? $players[$house['id']] : array();

        $houseRent = null;
        if($db->hasColumn('houses', 'guild') && $house['guild'] == 1 && $house['owner'] != 0)
        {
            $guild = new OTS_Guild();
            $guild->load($house['owner']);
            $houseRent = 'Rented by ' . getGuildLink($guild->getName());
        }
        else
        {
            if(!empty($owner['name']))
                $houseRent = 'Rented by ' . getPlayerLink($owner['name']);
            else if($hasHouseAuctions && !empty($house['bidder_name']))
                $houseRent = 'Auction by ' . getPlayerLink($house['bidder_name']) . ' (' . (int)$house['highest_bid'] . ' gold)';
            else
                $houseRent = 'Free';
        }

        $houses[] = array('owner' => $owner, 'name' => $house['name'], 'size' => $house['size'], 'rent' => $house['rent'], 'rentedBy' => $houseRent);
    }

    $housesSearch = true;
}

$guild = $db->hasColumn('houses', 'guild') ? ' or guildhall' : '';
$twig->display('houses.html.twig', array(
    'state' => $state,
    'order' => $order,
    'type' => $type,
    'houseType' => $type == 'guildhalls' ? 'Guildhalls' : 'Houses and Flats',
    'townName' => isset($townName) ? $townName : null,
    'townId' => isset($_POST['town']) ? $_POST['town'] : null,
    'guild' => $guild,
    'cleanOldHouse' => isset($cleanOld) ? $cleanOld : null,
    'housesSearch' => $housesSearch,
    'houses' => isset($houses) ? $houses : null
));
