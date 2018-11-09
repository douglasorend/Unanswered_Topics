<?php
/**********************************************************************************
* Subs-Unanswered.php                                                             *
***********************************************************************************
* This mod is licensed under the 2-clause BSD License, which can be found here:   *
*	http://opensource.org/licenses/BSD-2-Clause                                   *
***********************************************************************************
* This program is distributed in the hope that it is and will be useful, but	  *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY	  *
* or FITNESS FOR A PARTICULAR PURPOSE.											  *
**********************************************************************************/
if (!defined('SMF'))
	die('Hacking attempt...');

/**********************************************************************************
* The Main Function!!                                                             *
**********************************************************************************/
function UnansweredTopics()
{
	global $txt, $scripturl, $user_info, $context, $modSettings, $sourcedir, $smcFunc, $settings, $forum_version;
		
	// What's the limit for showing posts?
	$daysToGet = !empty($modSettings['unanswered_time_limit']) ? $modSettings['unanswered_time_limit'] : 0;
	$context['daysToGet'] = $daysToGet = max(0, isset($_GET['days']) ? $_GET['days'] : $daysToGet);
	$startTime = $daysToGet ? time() - ($daysToGet * 24 * 60 * 60) : 0;

	// Define limit start variable if not already defined.  Limit variable to "95":
	if (!isset($_REQUEST['start']))
		$_REQUEST['start'] = 0;
	elseif (isset($_REQUEST['start']) && $_REQUEST['start'] > 95)
		$_REQUEST['start'] = 95;
		
	if (isset($_GET['topics']))
	{
		$sort_methods = array(
			'subject' => 'm.subject',
			'starter' => 'IFNULL(mem.real_name, ms.poster_name)',
			'replies' => 't.num_replies',
			'views' => 't.num_views',
			'first_post' => 't.id_topic',
			'last_post' => 't.id_last_msg'
		);

		// The default is the most logical: newest first.
		if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
		{
			$context['sort_by'] = 'last_post';
			$_REQUEST['sort'] = 't.id_last_msg';
			$ascending = isset($_REQUEST['asc']);

			$context['querystring_sort_limits'] = $ascending ? ';asc' : '';
		}
		// But, for other methods the default sort is ascending.
		else
		{
			$context['sort_by'] = $_REQUEST['sort'];
			$_REQUEST['sort'] = $sort_methods[$_REQUEST['sort']];
			$ascending = !isset($_REQUEST['desc']);

			$context['querystring_sort_limits'] = ';sort=' . $context['sort_by'] . ($ascending ? '' : ';desc');
		}
		$context['sort_direction'] = $ascending ? 'up' : 'down';

		// Setup the default topic icons... for checking they exist and the like ;)
		$stable_icons = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'moved', 'recycled', 'wireless', 'clip');
		$context['icon_sources'] = array();
		foreach ($stable_icons as $icon)
			$context['icon_sources'][$icon] = 'images_url';

		$context['sub_template'] = 'unread';
		$context['showing_all_topics'] = isset($_GET['all']);
	}

	// Requested category(ies)?
	$auxboards = $boards = array();
	$context['querystring_board_limits'] = '';
	if (!empty($_REQUEST['c']))
	{
		// Find out all boards that such category(ies) contains
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
			$auxboards[] = $row['id_board'];
		$smcFunc['db_free_result']($request);
		$context['querystring_board_limits'] = ';c=' . $_REQUEST['c'];
	}
	// Did we request one or several boards?
	elseif (!empty($_REQUEST['boards']))
	{
		$auxboards = explode(',', $_REQUEST['boards']);
		$context['querystring_board_limits'] = ';boards=' . $_REQUEST['boards'];
	}
	// We requested nothing, so let's gather ALL board IDs
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_board
			FROM {db_prefix}boards'
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$auxboards[] = $row['id_board'];
		$smcFunc['db_free_result']($request);
	}
	$i = 0;

	// Quick and dirty filter to take out recycle board (if enabled)
	if (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 && in_array($modSettings['recycle_board'], $auxboards))
	{	
		foreach ($auxboards as $key => $value)
		{
			if ($value != $modSettings['recycle_board'])
			{
				$boards[] = array();
				$boards[$i]['id'] = $value;
				$boards[$i]['groups'] = '';
				$boards[$i]['access'] = false;
				$i++;
			}
		}
	}
	else
	{
		// direct copy
		foreach ($auxboards as $key => $value)
			$boards[] = array(
				'id' => $value,
				'groups' => '',
				'access' => false,
			);
	}
	unset ($auxboards);

	// Now, we might or might not have permission to access all we asked for, right?
	// We go back to mysql to get the membergroups allowed to access such boards
	// ---need a little trick to put all ids in a single array...
	$temp = array();
	foreach ($boards as $trash)
		$temp[] = $trash['id'];
		
	$request = $smcFunc['db_query']('', '
		SELECT id_board, member_groups
		FROM {db_prefix}boards
		WHERE id_board IN ({array_int:list_boards})',
		array(
			'list_boards' => $temp,
		)
	);
	// Fill our "boards" array with the corresponding member groups
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$key = null;
		$key = array_search($row['id_board'], $temp);
		if ($key !== NULL)
			$boards[$key]['groups'] = $row['member_groups'];
	}
	$smcFunc['db_free_result']($request);
	unset ($temp);
	
	// Now we have a list of board IDs and the corresponding accessible membergroups. Now, can we access this board or what?!
	// so, all our groups are already defined. why not use them?
	$user_membergroups = implode(',', $user_info['groups']);
	$user_membergroups_array = $user_info['groups'];

	// Everything is set. Let's run the "boards" array and compare the accessible membergroups with our owns.
	// if they match, then the board is ok to be accessed by us :)
	foreach ($boards as $i => $temp)
	{
		// we need to intersect 2 arrays, so the board membergroups ought to be an array...
		// if we are admin, OF COURSE we can access...
		$board_groups_array = explode(',', $temp['groups']);
		if ($context['user']['is_admin'] || (count(array_intersect($user_membergroups_array, $board_groups_array)) > 0))
			$boards[$i]['access'] = true;
		
	}
	
	// Count the total number of messages we can actually access:
	$boards_to_access = array();
	foreach ($boards as $temp)
	{
		if ($temp['access'])
			$boards_to_access[] = $temp['id'];
	}
	
	// Here we go. Count them!
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(t.id_topic) as total
		FROM {db_prefix}topics as t
			INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
		WHERE t.id_board IN ({array_int:list_boards})
			AND t.approved = 1
			AND t.locked = 0
			AND t.num_replies = 0' . (!empty($startTime) ? '
			AND m.poster_time > {int:time_limit}' : ''),
		array(
			'list_boards' => $boards_to_access,
			'time_limit' => $startTime,
		)
	);
	$data = $smcFunc['db_fetch_assoc']($request); 
	$total_messages = $data['total'];
	unset($data);
	$smcFunc['db_free_result']($request);

	// Now, the page index... thing! :)
	$context['UAT_smf21'] = $smf21 = (substr($forum_version, 0, 7) == 'SMF 2.1');
	loadTemplate('Unanswered' . ($smf21 ? '21' : '20'));
	$context['page_title'] = $txt['recent_posts'] = $txt['unanswered_topics'];
	$ua_link = $scripturl . '?action=unanswered' . (isset($_GET['topics']) ? ';topics' : '');
	$context['page_index'] = constructPageIndex($ua_link . $context['querystring_board_limits'], $_REQUEST['start'], min(100, $total_messages), 10);

	// Linktree
	$context['linktree'][] = array(
		'url' => $ua_link,
		'name' => $context['page_title']
	);

	// Nothing here... Or at least, nothing you can see...
	$context['posts'] = array();
	if (empty($total_messages))
		return;
		
	// FINALLY!!! Let's get ourselves some posts, shall we? :)
	$request = $smcFunc['db_query']('', '
		SELECT
			m.id_msg, m.subject, m.smileys_enabled, m.poster_time, m.body, m.id_topic, t.id_board, b.id_cat,
			b.name AS bname, c.name AS cname, t.num_replies, m.id_member, m.id_member AS id_first_member,
			IFNULL(mem.real_name, m.poster_name) AS first_poster_name, t.id_first_msg, m.icon, 
			IFNULL(mem.real_name, m.poster_name) AS poster_name, t.id_last_msg' . (isset($_GET['topics']) ? ', 
			t.num_views as views, t.is_sticky, m.id_msg AS new_from, t.id_poll' : '') . '
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE t.id_board IN ({array_int:list_boards})
			AND t.approved = 1
			AND t.locked = 0
			AND t.num_replies = 0' . (!empty($startTime) ? '
			AND m.poster_time > {int:time_limit}' : '') . '
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'current_member' => $user_info['id'],
			'list_boards' => $boards_to_access,
			'time_limit' => $startTime,
			'offset' => $_REQUEST['start'],
			'limit' => 10,
			'sort' => !isset($_GET['topics']) ? 'm.id_msg DESC' : $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
		)
	);
	$counter = $_REQUEST['start'] + 1;
	$board_ids = array('own' => array(), 'any' => array());
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Censor everything, then parse the message:
		censorText($row['body']);
		censorText($row['subject']);
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
				'href' => $scripturl . '?board = ' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board = ' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel = "nofollow">' . $row['subject'] . '</a>',
			'start' => $row['num_replies'],
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'poster' => array(
				'id' => $row['id_first_member'],
				'name' => $row['first_poster_name'],
				'href' => empty($row['id_first_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_first_member'],
				'link' => empty($row['id_first_member']) ? $row['first_poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_first_member'] . '">' . $row['first_poster_name'] . '</a>'
			),
			'message' => $row['body'],
			'can_reply' => false,
			'can_mark_notify' => false,
			'can_delete' => false,
			'icon' => $row['icon'],
			'is_posted_in' => $row['id_first_member'] == $user_info['id'],
			'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] ==  $row['id_msg']) && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()),
			'css_class' => 'windowbg',
		);

		// Add some stuff for topic list of unanswered topics:
		if (isset($_GET['topics']))
		{
			$context['posts'][$row['id_msg']] += array(
				'replies' => 0,
				'views' => $row['views'],
				'pages' => '',
				'class' => '',
				'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . ';topicseen#new',
				'is_sticky' => !empty($modSettings['enableStickyTopics']) && !empty($row['is_sticky']),
				'icon' => $row['icon'],
				'icon_url' => $settings[$context['icon_sources'][$row['icon']]] . '/post/' . $row['icon'] . '.gif',
				'new_from' => $row['new_from'],
				'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . ';topicseen#new',
				'is_very_hot' => false,
				'is_hot' => false,
				'is_poll' => $modSettings['pollMode'] == '1' && $row['id_poll'] > 0,
				'is_locked' => false,
			);

			// We need to check the topic icons exist... you can never be too sure!
			if (empty($modSettings['messageIconChecks_disable']))
			{
				if (!isset($context['icon_sources'][$row['icon']]))
					$context['icon_sources'][$row['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['icon'] . '.gif') ? 'images_url' : 'default_images_url';
			}

			if ($smf21)
			{
				$context['posts'][$row['id_msg']]['css_class'] = 'windowbg' . ($row['is_sticky'] ? ' sticky' : '');
				$row['body'] = strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']), array('<br>' => '&#10;')));
				if ($smcFunc['strlen']($row['body']) > 128)
					$row['body'] = $smcFunc['substr']($row['body'], 0, 128) . '...';
				$context['posts'][$row['id_msg']]['preview'] = $row['body'];
				$context['posts'][$row['id_msg']]['started_by'] = sprintf($txt['topic_started_by'], $context['posts'][$row['id_msg']]['poster']['link'], $context['posts'][$row['id_msg']]['board']['link']);
			}
			else
				determineTopicClass($context['posts'][$row['id_msg']]);
		}
		else
		{
			if ($user_info['id'] == $row['id_first_member'])
				$board_ids['own'][$row['id_board']][] = $row['id_msg'];
			$board_ids['any'][$row['id_board']][] = $row['id_msg'];
		}
	}
	$smcFunc['db_free_result']($request);

	if (!isset($_GET['topics']))
	{
		// There might be - and are - different permissions between any and own.
		$permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
				'delete_own' => 'can_delete',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
				'mark_any_notify' => 'can_mark_notify',
				'delete_any' => 'can_delete',
			)
		);

		// Now go through all the permissions, looking for boards they can do it on.
		foreach ($permissions as $type => $list)
		{
			foreach ($list as $permission => $allowed)
			{
				// They can do it on these boards...
				$boards = boardsAllowedTo($permission);

				// If 0 is the only thing in the array, they can do it everywhere!
				if (!empty($boards) && $boards[0] == 0)
					$boards = array_keys($board_ids[$type]);

				// Go through the boards, and look for posts they can do this on.
				foreach ($boards as $board_id)
				{
					// Hmm, they have permission, but there are no topics from that board on this page.
					if (!isset($board_ids[$type][$board_id]))
						continue;

					// Okay, looks like they can do it for these posts.
					foreach ($board_ids[$type][$board_id] as $counter)
						if ($type == 'any' || $context['posts'][$counter]['poster']['id'] == $user_info['id'])
							$context['posts'][$counter][$allowed] = true;
				}
			}
		}

		$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));
		foreach ($context['posts'] as $counter => $dummy)
		{
			// Some posts - the first posts - can't just be deleted.
			$context['posts'][$counter]['can_delete'] &= $context['posts'][$counter]['delete_possible'];

			// And some cannot be quoted...
			$context['posts'][$counter]['can_quote'] = $context['posts'][$counter]['can_reply'] && $quote_enabled;
		}
	}
}

?>