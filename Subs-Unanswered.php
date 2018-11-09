<?php
/********************************************************************************
* Subs-Unanswered.php - Hooks for Unanswered Topics mod
*********************************************************************************
* This program is distributed in the hope that it is and will be useful, but
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY
* or FITNESS FOR A PARTICULAR PURPOSE,
**********************************************************************************/
if (!defined('SMF'))
	die('Hacking attempt...');

function UTM_Load()
{
	loadLanguage('Unanswered');
}

function UTM_Actions(&$actions)
{
	$actions['unanswered'] = array('Unanswered.php', 'UnansweredTopics');
}

function UTM_Settings(&$settings)
{
	global $txt;
	$settings[] = array('int', 'unanswered_time_limit', 'postinput' => $txt['days_word']);
}

function UTM_Buffer($buffer)
{
	global $scripturl, $modSettings, $txt, $forum_version, $user_info;
	
	if (!$user_info['id'])
		return $buffer;
	if (substr($forum_version, 0, 7) == 'SMF 2.1')
	{
		$search = '<a href="' . $scripturl . '?action=unreadreplies" title="' . $txt['show_unread_replies'] . '">' . $txt['unread_replies'] . '</a>';
		$insert = '<a href="' . $scripturl . '?action=unanswered" title="' . $txt['unanswered_topics'] . '">' . $txt['unanswered_topics'] . '</a>';
	}
	else
	{
		$search = '<li><a href="' . $scripturl . '?action=unreadreplies">' . $txt['show_unread_replies'] . '</a></li>';
		$insert = '<li><a href="' . $scripturl . '?action=unanswered">' . (!empty($modSettings['unanswered_time_limit']) ? sprintf($txt['show_unanswered_topics_limit'], $modSettings['unanswered_time_limit']) : $txt['show_unanswered_topics']) . '</a></li>';
	}
	return str_replace($search, $search . $insert, $buffer);
}

?>