<?php
// If we have found SSI.php and we are outside of SMF, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF')) // If we are outside SMF and can't find SSI.php, then throw an error
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as SMF\'s SSI.php.');
require_once($sourcedir.'/Subs-Admin.php');

// Insert the current board path as the default server path for subforums:
if (!isset($modSettings['unanswered_time_limit']))
	updateSettings(array(
		'unanswered_time_limit' => 90,
	));

if (SMF == 'SSI')
	echo 'Congratulations! You have successfully installed the settings for this mod!';
?>