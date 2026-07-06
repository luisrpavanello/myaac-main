<?php

$topPlayers = getTopPlayers(5);
foreach($topPlayers as &$player) {
	if($config['online_outfit']) {
		$player['outfit'] = getVocationImage($player['vocation']);
	}
}

$twig->display('highscores.html.twig', array(
	'topPlayers' => $topPlayers
));
