<?php
$title = 'Creatures';
$race = filter_input(INPUT_GET, 'race');

if (empty($race)) {
	$monsterquery = $SQL->query('SELECT `boostname`, `looktype`, `lookfeet` , `looklegs` , `lookhead` , `lookbody` , `lookaddons` , `lookmount`   FROM `boosted_creature`')->fetch();

	$monstername = $monsterquery['boostname'];
	$monstertype = $monsterquery['looktype'];
	$monsterfeet = $monsterquery['lookfeet'];
	$monsterlegs = $monsterquery['looklegs'];
	$monsterhead = $monsterquery['lookhead'];
	$monsterbody = $monsterquery['lookbody'];
	$monsteraddons = $monsterquery['lookaddons'];
	$monstermount = $monsterquery['lookmount'];

	$replaces = [];
	$replaces['boosted_monster_name'] = $monsterquery['boostname'];
	$replaces['boosted_monster_uri'] = str_replace(' ', '', strtolower($monsterquery['boostname']));
	$replaces['boosted_monster_image'] = getLibraryCreatureImage($monsterquery['boostname']);

	echo $twig->render('library/library.html.twig', $replaces);

	return;
}

if (!ctype_alnum($race)) {
	echo 'Race contains illegal letters (a-z, A-Z and 0-9 only!).';
	return;
}

$file = 'library/' . $race . '.html.twig';
if (file_exists(SYSTEM . 'templates/' . $file)) {
	echo $twig->render($file);
}
