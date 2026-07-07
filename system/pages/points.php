<?php
global $title;

defined('MYAAC') or die('Direct access not allowed!');

$title = 'Payment Center';
$_GET['type'] = $_GET['type'] ?? 'coins';
require SYSTEM . 'pages/donate.php';
