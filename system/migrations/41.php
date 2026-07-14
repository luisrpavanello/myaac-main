<?php

// Seed a useful default FAQ for local Numenor/MyAAC installs.
if($db->hasTable(TABLE_PREFIX . 'faq')) {
	$faqs = [
		[
			'question' => 'What is this website?',
			'answer' => 'This is the local Numenor website. You can create an account, manage characters, check highscores, houses, guilds, forum, shop and server information here.',
		],
		[
			'question' => 'How do I create my account?',
			'answer' => 'Open <a href="?account/create">Create Account</a>, fill in your information and submit the form. After that, go to <a href="?account/manage">My Account</a> to create your characters.',
		],
		[
			'question' => 'Which client should I download?',
			'answer' => 'Use the <a href="?downloads">Downloads</a> page to get the client configured for this server. If login does not work, make sure you are using the correct client version.',
		],
		[
			'question' => 'How do I protect my account?',
			'answer' => 'Register your account in <a href="?account/manage">My Account</a> to generate a recovery key. Store that key safely; it is used to recover access if you lose your password.',
		],
		[
			'question' => 'How do I see who is online?',
			'answer' => 'The <a href="?online">Who is Online?</a> page shows connected players, levels and vocations. The top bar also updates the current server status.',
		],
		[
			'question' => 'How do I bid on a house?',
			'answer' => 'Open <a href="?houses">Houses</a>, choose an available house and submit your bid with a premium character. When the auction ends, the house is assigned according to the server rules.',
		],
		[
			'question' => 'How do I create a guild?',
			'answer' => 'Open <a href="?guilds">Guilds</a> and use the create guild option. The leader must meet the level and premium requirements configured for this server.',
		],
		[
			'question' => 'How do points and the shop work?',
			'answer' => 'Open <a href="?points">Buy Points</a> to see coin packages and <a href="?gifts">Shop Offer</a> to check available offers. Your purchase history is available at <a href="?gifts/history">Shop History</a>.',
		],
		[
			'question' => 'Where can I see rates, stages and server information?',
			'answer' => 'Use <a href="?server-info">Server Info</a>, <a href="?exp-stages">Exp Stages</a> and <a href="?exp-table">Exp Table</a> to check progression, experience and general server details.',
		],
		[
			'question' => 'Where can I get help?',
			'answer' => 'Check the <a href="?team">Support List</a> to find staff members. You can also use the server Discord whenever it is available in the menu or top bar.',
		],
	];

	$query = $db->query('SELECT MAX(`ordering`) AS `ordering` FROM `' . TABLE_PREFIX . 'faq`;')->fetch();
	$ordering = isset($query['ordering']) ? (int)$query['ordering'] + 1 : 0;

	foreach($faqs as $faq) {
		if($db->select(TABLE_PREFIX . 'faq', ['question' => $faq['question']]) === false) {
			$db->insert(TABLE_PREFIX . 'faq', [
				'question' => $faq['question'],
				'answer' => $faq['answer'],
				'ordering' => $ordering++,
			]);
		}
	}
}
?>
