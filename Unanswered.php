<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');


// Topics with no replies...
function UnansweredTopics()
{
	global $txt, $scripturl, $user_info, $context, $modSettings, $sourcedir, $smcFunc, $board;
		
	//What's the limit for showing posts?
	$daysToGet = 100; //This parameter should come from ACP. Maybe later :P
	$timeLimit = time() - ($daysToGet * 24 * 60 * 60);

	if (isset($_REQUEST['start']) && $_REQUEST['start'] > 95)
		$_REQUEST['start'] = 95;

		
	//Requested Category and board?! NOT SUPPORTED!!!
	if (!empty($_REQUEST['c']) && !empty($_REQUEST['boards']))
		return;
		
	$auxboards = array();
	$boards = array();
	//Requested category(ies)?
	if (!empty($_REQUEST['c']))
	{
		//Find out all boards that such category(ies) contains
		$categs = explode(',', $_REQUEST['c']);
		$request = $smcFunc['db_query']('', '
			SELECT id_board
			FROM {db_prefix}boards
			WHERE id_cat IN ({array_int:current_cat})',
			array(
				'current_cat' => $categs,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$auxboards[] = $row['id_board'];
		}
		$smcFunc['db_free_result']($request);
		
		// echo '<pre>';
		// print_r($auxboards);
		// echo '</pre>';
	}
	//Did we request one or several boards?
	elseif (!empty($_REQUEST['boards']))
	{
		$auxboards = explode(',', $_REQUEST['boards']);
		// echo '<pre>';
		// print_r($boards);
		// echo '</pre>';
	}
	//We requested nothing, so let's gather ALL board IDs
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_board
			FROM {db_prefix}boards'
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$auxboards[] = $row['id_board'];
		}
		$smcFunc['db_free_result']($request);
		// echo '<pre>';
		// print_r($auxboards);
		// echo '</pre>';
	}
	$i=0;
	//Quick and dirty filter to take out recycle board (if enabled)
	if (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 && in_array($modSettings['recycle_board'], $auxboards))
	{	
		foreach($auxboards as $key => $value)
		{
			if ($value != $modSettings['recycle_board'])
			{
				$boards[]=array();
				$boards[$i]['id'] = $value;
				$boards[$i]['groups'] = '';
				$boards[$i]['access'] = false;
				$i++;
			}
		}
	}
	else
	{
		//direct copy
		//$boards = $auxboards;
		foreach($auxboards as $key => $value)
		{
			$boards[]=array();
			$boards[$i]['id'] = $value;
			$boards[$i]['groups'] = '';
			$boards[$i]['access'] = false;			
			$i++;
		}
	}
	//don't need you anymore
	unset ($auxboards);
	unset ($i);

	// echo '<pre>';
	// print_r($boards);
	// echo '</pre>';

	//Now, we might or might not have permission to access all we asked for, right?
	//We go back to mysql to get the membergroups allowed to access such boards
	//---need a little trick to put all ids in a single array...
	$temp = array();
	foreach ($boards as $trash)
		$temp[] = $trash['id'];

	// echo '<pre>';
	// print_r($temp);
	// echo '</pre>';
		
	$request = $smcFunc['db_query']('', '
		SELECT id_board, member_groups
		FROM {db_prefix}boards
		WHERE id_board IN ({array_int:list_boards})',
		array(
			'list_boards' => $temp,
		)
	);
	//Fill our "boards" array with the corresponding member groups
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$key = null;
		$key = array_search($row['id_board'], $temp);
		// echo $key . '/ ' . $row['id_board'] . '/ ' . $row['member_groups'] . '<br>';
		if ($key !== NULL)
		{
			$boards[$key]['groups'] = $row['member_groups'];
		}
	}
	$smcFunc['db_free_result']($request);
	//we don't need you anymore
	unset ($temp);
	// echo '<pre>';
	// print_r($boards);
	// echo '</pre>';
	
	//Now we have a list of board IDs and the corresponding accessible membergroups. Now, can we access this board or what?!
	//so, all our groups are already defined. why not use them?
	$user_membergroups = implode(',', $user_info['groups']);
	$user_membergroups_array = $user_info['groups'];
//	echo $user_membergroups .'<br>';
	
	//Everything is set. Let's run the "boards" array and compare the accessible membergroups with our owns.
	//if they match, then the board is ok to be accessed by us :)
	foreach ($boards as &$temp)
	{
		//we need to intersect 2 arrays, so the board membergroups ought to be an array...
		//if we are admin, OF COURSE we can access...
		$board_groups_array = explode(',', $temp['groups']);
		if ($context['user']['is_admin'] || (count(array_intersect($user_membergroups_array, $board_groups_array)) > 0))
			$temp['access'] = true;
		
	}
	//make sure any write to $temp doesn't affect the last array entry!
	unset($temp);
	// echo '<pre>';
	// print_r($boards);
	// echo '</pre>';
	
	//Finally... pfeww!
	//Now we have to COUNT the total number of messages we can actually access. This is needed for the page index... thing!
	$boards_to_access = array();
	foreach ($boards as &$temp)
	{
		if ($temp['access'])
			$boards_to_access[] = $temp['id'];
	}
	// echo '<pre>';
	// print_r($boards_to_access);
	// echo '</pre>';
	
	//Here we go. Count them!
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(t.id_topic) as total
		FROM {db_prefix}topics as t
			INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
		WHERE t.id_board IN ({array_int:list_boards})
			AND t.approved = 1
			AND t.num_replies = 0
			AND t.locked = 0
			AND m.poster_time > {int:time_limit}',
		array(
			'list_boards' => $boards_to_access,
			'time_limit' => $timeLimit,
		)
	);
	$data = $smcFunc['db_fetch_assoc']($request); 
	$total_messages = $data['total'];
	unset($data);
	$smcFunc['db_free_result']($request);
	// echo '<pre>';
	// echo $total_messages;
	// echo '</pre>';

	//Now, the page index... thing! :)
	loadTemplate('Unanswered');
	$context['page_title'] = $txt['unanswered_topics'];
	$context['page_index'] = constructPageIndex($scripturl . '?action=unanswered', $_REQUEST['start'], min(100, $total_messages), 10, false);
	//Linktree
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=unanswered',
		'name' => $context['page_title']
	);

	// Nothing here... Or at least, nothing you can see...
	if ($total_messages < 1)
	{
		$context['posts'] = array();
		return;
	}
	
	//FINALLY!!! Let's get ourselves some posts, shall we? :)
	$request = $smcFunc['db_query']('', '
		SELECT
			m.id_msg, m.subject, m.smileys_enabled, m.poster_time, m.body, m.id_topic, t.id_board, b.id_cat,
			b.name AS bname, c.name AS cname, t.num_replies, m.id_member, m.id_member AS id_first_member,
			IFNULL(mem.real_name, m.poster_name) AS first_poster_name, t.id_first_msg,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, t.id_last_msg
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE t.id_board IN ({array_int:list_boards})
		    AND t.id_first_msg = m.id_msg
			AND t.approved = 1
			AND t.locked = 0
			AND t.num_replies = 0
			AND m.poster_time > {int:time_limit}
		ORDER BY m.id_msg DESC
		LIMIT {int:offset}, {int:limit}',
		array(
			'list_boards' => $boards_to_access,
			'time_limit' => $timeLimit,
			'offset' => $_REQUEST['start'],
			'limit' => 10,
		)
	);
	$counter = $_REQUEST['start'] + 1;
	$context['posts'] = array();
	$board_ids = array('own' => array(), 'any' => array());
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Censor everything.
		censorText($row['body']);
		censorText($row['subject']);

		// BBC-atize the message.
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// And build the array.
		$context['posts'][$row['id_msg']] = array(
			'id' => $row['id_msg'],
			'counter' => $counter++,
			'alternate' => $counter % 2,
			'category' => array(
				'id' => $row['id_cat'],
				'name' => $row['cname'],
				'href' => $scripturl . '#c' . $row['id_cat'],
				'link' => '<a href="' . $scripturl . '#c' . $row['id_cat'] . '">' . $row['cname'] . '</a>'
			),
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['bname'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>',
			'start' => $row['num_replies'],
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'first_poster' => array(
				'id' => $row['id_first_member'],
				'name' => $row['first_poster_name'],
				'href' => empty($row['id_first_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_first_member'],
				'link' => empty($row['id_first_member']) ? $row['first_poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_first_member'] . '">' . $row['first_poster_name'] . '</a>'
			),
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'message' => $row['body'],
			'can_reply' => false,
			'can_mark_notify' => false,
			'can_delete' => false,
			'can_quote' => false,
			'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()),
		);

		if ($user_info['id'] == $row['id_first_member'])
			$board_ids['own'][$row['id_board']][] = $row['id_msg'];
		$board_ids['any'][$row['id_board']][] = $row['id_msg'];
	}
	$smcFunc['db_free_result']($request);
}

?>