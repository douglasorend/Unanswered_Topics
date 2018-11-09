<?php
/********************************************************************************
* Unanswered.php - Subs of the Unanswered Topics mod
*********************************************************************************
* This program is distributed in the hope that it is and will be useful, but
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY
* or FITNESS FOR A PARTICULAR PURPOSE,
**********************************************************************************/
if (!defined('SMF'))
	die('Hacking attempt...');

/********************************************************************************
* Functions necessary to list & mark our "important topics" to the user:
********************************************************************************/
function UnansweredTopics()
{
	global $context, $txt, $scripturl, $modSettings, $smcFunc, $sourcedir;

	// Set up for listing the unanswered topics:
	loadLanguage('Unanswered');
	$context['page_title' ] = $txt['unanswered_topics'];
	$context['sub_template'] = 'unanswered_topics';
	$context['delete_own'] = $context['delete_any'] = array();
	$context['can_delete'] = false;
	$context['unanswered_member'] = isset($_GET['u']) ? (int) $_GET['u'] : false;

	// Are we removing the topic?
	if (isset($_GET['save']) && isset($_POST['remove_submit']))
	{
		// Remove every topic we have permission to:
		checkSession();
		if (!empty($_POST['remove']))
		{
			require_once($sourcedir . '/RemoveTopic.php');
			$context['unanswered_topics'] = $topics = array();
			foreach ($_POST['remove'] AS $id_topic => $ignored)
			{
				if (in_array($id_topic, $_SESSION['unanswered_can_delete']))
					$topics[] = $id_topic;
				else
					$context['unanswered_topics'][] = $id_topic;
			}
			removeTopics($topics);
		}

		// If there are topics left, notify user about no permission to delete:
		if (empty($context['unanswered_topics']))
			redirectExit('action=unanswered;sort=' . $_POST['sort'] . (!empty($_POST['desc']) ? ';desc' : '') . (!empty($_POST['start']) ? ';start=' . $_POST['start'] : ''));
		$context['sub_template'] = 'unanswered_cannot_delete';
		return;
	}
	$_SESSION['unanswered_can_delete'] = array();

	// Set the options for the list component.
	$topic_listOptions = array(
		'id' => 'unanswered_topics',
		'title' => $txt['unanswered_topics'],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'base_href' => $scripturl . '?action=unanswered',
		'default_sort_col' => 'lastpost',
		'default_sort_dir' => 'desc',
		'no_items_label' => sprintf($txt['unanswered_topics_visit_none'], $modSettings['unanswered_time_limit']),
		'get_items' => array(
			'function' => 'UTM_Get_Topics',
		),
		'get_count' => array(
			'function' => 'UTM_Topics_Count',
		),
		'columns' => array(
			'icon' => array(
				'header' => array(
					'value' => '',
				),
				'data' => array(
					'function' => 'UTM_Icon',
					'style' => 'text-align: center; width: 30px',
				),
			),
			'subject' => array(
				'header' => array(
					'value' => $txt['topics'],
				),
				'data' => array(
					'function' => 'UTM_Subject',
				),
				'sort' => array(
					'default' => 'b.name, m.subject',
					'reverse' => 'b.name DESC, m.subject DESC',
				),
			),
			'views' => array(
				'header' => array(
					'value' => $txt['views'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return comma_format($rowData["num_views"]);
					'),
					'style' => 'text-align: center; width: 7%',
				),
				'sort' => array(
					'default' => 't.num_views',
					'reverse' => 't.num_views DESC',
				),
			),
			'lastpost' => array(
				'header' => array(
					'value' => $txt['last_post'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return timeformat($rowData["posted"]);
					'),
					'style' => 'width: 30%',
				),
				'sort' => array(
					'default' => 'm.poster_time',
					'reverse' => 'm.poster_time DESC',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
				),
				'data' => array(
					'function' => 'UTM_Checkbox',
					'style' => 'text-align: center; width: 30px',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=unanswered;save',
			'include_sort' => true,
			'include_start' => true,
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="remove_submit" class="button_submit" value="' . $txt['quick_mod_remove'] . '" onclick="return confirm(\'' . $txt['unanswered_remove_topics'] . '\');" />',
				'style' => 'text-align: right;',
			),
		),
	);

	// Create the list.
	require_once($sourcedir . '/Subs-List.php');
	createList($topic_listOptions);
	
	// Can we delete ANY threads in the list?  If not, remove the checkboxes!!!
	if (empty($context['can_delete']))
	{
		foreach ($context['unanswered_topics']['headers'] as $num => $element)
		{
			if ($element['id'] == 'check')
				unset($context['unanswered_topics']['headers'][$num]);
		}
		foreach ($context['unanswered_topics']['rows'] as $num => $element)
			unset($context['unanswered_topics']['rows'][$num]['check']);
		unset($context['unanswered_topics']['additional_rows']);
	}
}

/********************************************************************************
* Functions doing our database queries:
********************************************************************************/
function UTM_Topics_Count()
{
	global $smcFunc, $modSettings, $context;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(t.important) AS count
		FROM {db_prefix}topics AS t
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE {query_see_board}' . (!empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']) ? '
			AND b.id_board != {int:recycle_board}' : '') . (!empty($context['unanswered_member']) ? '
			AND m.id_member = {int:id_member}' : '') . '
			AND t.id_first_msg = t.id_last_msg
			AND t.locked = {int:not_locked}
			AND m.poster_time > {int:time_limit}',
		array(
			'not_locked' => 0,
			'id_member' => $context['unanswered_member'],
			'time_limit' => time() - (isset($modSettings['unanswered_time_limit']) ? $modSettings['unanswered_time_limit'] : 90) * 86400,
			'recycle_board' => $modSettings['recycle_board'],
		)
	);
	list($count) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	return $count;
}

function UTM_Get_Topics($start, $items_per_page, $sort)
{
	global $smcFunc, $modSettings, $context, $user_info;

	$request = $smcFunc['db_query']('', '
		SELECT
			t.id_topic, t.num_replies, t.num_views, t.id_first_msg, m.id_msg_modified,
			' . ($user_info['is_guest'] ? '0' : 'IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1') . ' AS new_from,
			m.id_member, IFNULL(mem.real_name, m.poster_name) AS poster, m.icon,
			m.subject AS subject, m.poster_time AS posted, b.id_board, b.name AS board_name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . ($user_info['is_guest'] ? '' : '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})') . '
		WHERE {query_see_board}' . (!empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']) ? '
			AND b.id_board != {int:recycle_board}' : '') . (!empty($context['unanswered_member']) ? '
			AND m.id_member = {int:id_member}' : '') . '
			AND t.id_first_msg = t.id_last_msg
			AND t.locked = {int:not_locked}
			AND m.poster_time > {int:time_limit}
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:per_page}',
		array(
			'current_member' => $user_info['id'],
			'sort' => $sort,
			'start' => $start,
			'per_page' => $items_per_page,
			'not_locked' => 0,
			'time_limit' => time() - (isset($modSettings['unanswered_time_limit']) ? $modSettings['unanswered_time_limit'] : 90) * 86400,
			'recycle_board' => $modSettings['recycle_board'],
			'id_member' => $context['unanswered_member'],
		)
	);
	$topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$topics[] = $row;
	$smcFunc['db_free_result']($request);
	return $topics;
}

/********************************************************************************
* Functions necessary to properly format the data for the display:
********************************************************************************/
function UTM_Icon(&$rowData)
{
	global $context, $settings;
	
	if (!isset($context['icon_sources'][$rowData['icon']]))
		$context['icon_sources'][$rowData['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $rowData['icon'] . '.gif') ? 'images_url' : 'default_images_url';
	return '<img src="' . $settings[$context['icon_sources'][$rowData['icon']]] . '/post/' . $rowData['icon'] . '.gif" alt="" />';
}

function UTM_Subject(&$rowData)
{
	global $scripturl, $txt, $settings, $forum_version;

	$board = '<strong><a href="' . $scripturl . '?board=' . $rowData['id_board'] . '.0">' . $rowData['board_name'] . '</a></strong>';
	$topic = '<strong><a href="' . $scripturl . '?topic=' . $rowData['id_topic'] . '.0">' . $rowData['subject'] . '</a></strong>';
	$user = '<strong><a href="' . $scripturl . '?action=profile;user=' . $rowData['id_member'] . '">' . $rowData['poster'] . '</a></strong>';
	if (substr($forum_version, 0, 7) == 'SMF 2.1')
		$unread = $rowData['new_from'] >= $rowData['id_msg_modified'] ? '' : ' <span class="new_posts">' . $txt['new'] . '</span>';
	else
		$unread = $rowData['new_from'] >= $rowData['id_msg_modified'] ? '' : ' <img src="' . $settings['lang_images_url'] . '/new.gif" alt="' . $txt['new'] . '" />';
	return $board . ' &raquo; ' . $topic . $unread . '<div class="smalltext">' . $txt['started_by'] . ' ' . $user . '</div>';
}

function UTM_Checkbox(&$rowData)
{
	global $context, $user_info;
	
	if (!isset($context['delete_own'][$rowData['id_board']]))
	{
		$own = $context['delete_own'][$rowData['id_board']] = allowedTo('delete_own', $rowData['id_board']);
		$any = $context['delete_any'][$rowData['id_board']] = allowedTo('delete_any', $rowData['id_board']);
	}
	else
	{
		$own = $context['delete_own'][$rowData['id_board']];
		$any = $context['delete_any'][$rowData['id_board']];
	}
	$context['can_delete'] |= ($delete = ($own && $rowData['id_member'] == $user_info['id']) || $any);
	if ($delete)
	{
		$_SESSION['unanswered_can_delete'][] = $rowData['id_topic'];
		return '<input type="checkbox" name="remove[' . $rowData['id_topic'] . ']" class="input_check" />';
	}
}

/********************************************************************************
* Our templates functions:
********************************************************************************/
function template_unanswered_topics()
{
	template_show_list('unanswered_topics');
}

function template_unanswered_cannot_delete()
{
}

?>