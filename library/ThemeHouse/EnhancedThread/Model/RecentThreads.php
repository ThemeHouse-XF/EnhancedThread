<?php

//######################## Enhanced Threads By ThemeHouse ###########################
class ThemeHouse_EnhancedThread_Model_RecentThreads extends XenForo_Model
{
    /**
	 * Constants to allow joins to extra tables in certain queries
	 *
	 * @var integer Join user table
	 * @var integer Join node table
	 * @var integer Join post table
	 * @var integer Join user table to fetch avatar info of first poster
	 * @var integer Join forum table to fetch forum options
	 */
	const FETCH_USER = 0x01;
	const FETCH_FORUM = 0x02;
	const FETCH_FIRSTPOST = 0x04;
	const FETCH_AVATAR = 0x08;
	const FETCH_DELETION_LOG = 0x10;
	const FETCH_FORUM_OPTIONS = 0x20;
	const FETCH_LAST_POST_AVATAR = 0x40;
	
    /**
	 * Gets threads that match the given conditions.
	 *
	 * @param array $conditions Conditions to apply to the fetching
	 * @param array $fetchOptions Collection of options that relate to fetching
	 *
	 * @return array Format: [thread id] => info
	 */
	public function getThreads(array $conditions, array $fetchOptions = array())
	{
		$whereConditions = $this->prepareThreadConditions($conditions, $fetchOptions);
        $sqlClauses = $this->prepareThreadFetchOptions($fetchOptions);
		$orderClause = $this->prepareThreadOrderOptions($fetchOptions, 'thread.post_date DESC');
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$forceIndex = (!empty($fetchOptions['forceThreadIndex']) ? 'FORCE INDEX (' . $fetchOptions['forceThreadIndex'] . ')' : '');

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT thread.*
					' . $sqlClauses['selectFields'] . '
				FROM xf_thread AS thread ' . $forceIndex . '
				' . $sqlClauses['joinTables'] . '
				WHERE ' . $whereConditions . '
				' . $orderClause . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'thread_id');
	}
	
	/**
	 * Prepair order options.
	 */
	public function prepareThreadOrderOptions(array &$fetchOptions, $defaultOrderSql = '')
    {
        $choices = array(
            'title' => 'sticky DESC, thread.title',
            'view_count' => 'sticky DESC, thread.view_count',
			'reply_count' => 'sticky DESC, thread.reply_count',
            'post_date' => 'sticky DESC, thread.post_date',
			'last_post_date' => 'sticky DESC, thread.last_post_date',
			'first_post_likes' => 'sticky DESC, thread.first_post_likes',
        );

        return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
    }
	
	/**
	 * Checks the 'join' key of the incoming array for the presence of the FETCH_x bitfields in this class
	 * and returns SQL snippets to join the specified tables if required
	 *
	 * @param array $fetchOptions containing a 'join' integer key build from this class's FETCH_x bitfields
	 *
	 * @return array Containing selectFields, joinTables, orderClause keys.
	 * 		Example: selectFields = ', user.*, foo.title'; joinTables = ' INNER JOIN foo ON (foo.id = other.id) '; orderClause = ORDER BY x.y
	 */
	public function prepareThreadFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';

		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';

			switch ($fetchOptions['order'])
			{
				case 'title':
				case 'post_date':
				case 'view_count':
					$orderBy = 'thread.' . $fetchOptions['order'];
					break;

				case 'reply_count':
				case 'first_post_likes':
					$orderBy = 'thread.' . $fetchOptions['order'];
					$orderBySecondary = ', thread.last_post_date DESC';
					break;

				case 'last_post_date':
				default:
					$orderBy = 'thread.last_post_date';
			}
			if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] == 'desc')
			{
				$orderBy .= ' DESC';
			}
			else
			{
				$orderBy .= ' ASC';
			}

			$orderBy .= $orderBySecondary;
		}

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					user.*, IF(user.username IS NULL, thread.username, user.username) AS username';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = thread.user_id)';
			}
			else if ($fetchOptions['join'] & self::FETCH_AVATAR)
			{
				$selectFields .= ',
					user.gender, user.avatar_date, user.gravatar';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = thread.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_LAST_POST_AVATAR)
			{
				$selectFields .= ',
					last_post_user.gender AS last_post_gender,
					last_post_user.avatar_date AS last_post_avatar_date,
					last_post_user.gravatar AS last_post_gravatar,
					IF(last_post_user.username IS NULL, thread.last_post_username, last_post_user.username) AS last_post_username';
				$joinTables .= '
					LEFT JOIN xf_user AS last_post_user ON
						(last_post_user.user_id = thread.last_post_user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_FORUM)
			{
				$selectFields .= ',
					node.title AS node_title, node.node_name';
				$joinTables .= '
					LEFT JOIN xf_node AS node ON
						(node.node_id = thread.node_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_FORUM_OPTIONS)
			{
				$selectFields .= ',
					forum.*,
					forum.last_post_id AS forum_last_post_id,
					forum.last_post_date AS forum_last_post_date,
					forum.last_post_user_id AS forum_last_post_user_id,
					forum.last_post_username AS forum_last_post_username,
					forum.last_thread_title AS forum_last_thread_title,
					thread.last_post_id,
					thread.last_post_date,
					thread.last_post_user_id,
					thread.last_post_username';
				$joinTables .= '
					LEFT JOIN xf_forum AS forum ON
						(forum.node_id = thread.node_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_FIRSTPOST)
			{
				$selectFields .= ',
					post.message, post.attach_count';
				$joinTables .= '
					LEFT JOIN xf_post AS post ON
						(post.post_id = thread.first_post_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_DELETION_LOG)
			{
				$selectFields .= ',
					deletion_log.delete_date, deletion_log.delete_reason,
					deletion_log.delete_user_id, deletion_log.delete_username';
				$joinTables .= '
					LEFT JOIN xf_deletion_log AS deletion_log ON
						(deletion_log.content_type = \'thread\' AND deletion_log.content_id = thread.thread_id)';
			}
		}

		if (isset($fetchOptions['readUserId']))
		{
			if (!empty($fetchOptions['readUserId']))
			{
				$autoReadDate = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);

				$joinTables .= '
					LEFT JOIN xf_thread_read AS thread_read ON
						(thread_read.thread_id = thread.thread_id
						AND thread_read.user_id = ' . $this->_getDb()->quote($fetchOptions['readUserId']) . ')';

				$joinForumRead = (!empty($fetchOptions['includeForumReadDate'])
					|| (!empty($fetchOptions['join']) && $fetchOptions['join'] & self::FETCH_FORUM)
				);
				if ($joinForumRead)
				{
					$joinTables .= '
						LEFT JOIN xf_forum_read AS forum_read ON
							(forum_read.node_id = thread.node_id
							AND forum_read.user_id = ' . $this->_getDb()->quote($fetchOptions['readUserId']) . ')';

					$selectFields .= ",
						GREATEST(COALESCE(thread_read.thread_read_date, 0), COALESCE(forum_read.forum_read_date, 0), $autoReadDate) AS thread_read_date";
				}
				else
				{
					$selectFields .= ",
						IF(thread_read.thread_read_date > $autoReadDate, thread_read.thread_read_date, $autoReadDate) AS thread_read_date";
				}
			}
			else
			{
				$selectFields .= ',
					NULL AS thread_read_date';
			}
		}

		if (isset($fetchOptions['replyBanUserId']))
		{
			if (!empty($fetchOptions['replyBanUserId']))
			{
				$selectFields .= ',
					IF(reply_ban.user_id IS NULL, 0,
						IF(reply_ban.expiry_date IS NULL OR reply_ban.expiry_date > '
						. $this->_getDb()->quote(XenForo_Application::$time) . ', 1, 0)) AS thread_reply_banned';
				$joinTables .= '
					LEFT JOIN xf_thread_reply_ban AS reply_ban
						ON (reply_ban.thread_id = thread.thread_id
						AND reply_ban.user_id = ' . $this->_getDb()->quote($fetchOptions['replyBanUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS thread_reply_banned';
			}
		}

		if (isset($fetchOptions['watchUserId']))
		{
			if (!empty($fetchOptions['watchUserId']))
			{
				$selectFields .= ',
					IF(thread_watch.user_id IS NULL, 0,
						IF(thread_watch.email_subscribe, \'watch_email\', \'watch_no_email\')) AS thread_is_watched';
				$joinTables .= '
					LEFT JOIN xf_thread_watch AS thread_watch
						ON (thread_watch.thread_id = thread.thread_id
						AND thread_watch.user_id = ' . $this->_getDb()->quote($fetchOptions['watchUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS thread_is_watched';
			}
		}

		if (isset($fetchOptions['forumWatchUserId']))
		{
			if (!empty($fetchOptions['forumWatchUserId']))
			{
				$selectFields .= ',
					IF(forum_watch.user_id IS NULL, 0, 1) AS forum_is_watched';
				$joinTables .= '
					LEFT JOIN xf_forum_watch AS forum_watch
						ON (forum_watch.node_id = thread.node_id
						AND forum_watch.user_id = ' . $this->_getDb()->quote($fetchOptions['forumWatchUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS forum_is_watched';
			}
		}

		if (isset($fetchOptions['draftUserId']))
		{
			if (!empty($fetchOptions['draftUserId']))
			{
				$selectFields .= ',
					draft.message AS draft_message, draft.extra_data AS draft_extra';
				$joinTables .= '
					LEFT JOIN xf_draft AS draft
						ON (draft.draft_key = CONCAT(\'thread-\', thread.thread_id)
						AND draft.user_id = ' . $this->_getDb()->quote($fetchOptions['draftUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					\'\' AS draft_message, NULL AS draft_extra';
			}
		}

		if (isset($fetchOptions['postCountUserId']))
		{
			if (!empty($fetchOptions['postCountUserId']))
			{
				$selectFields .= ',
					thread_user_post.post_count AS user_post_count';
				$joinTables .= '
					LEFT JOIN xf_thread_user_post AS thread_user_post
						ON (thread_user_post.thread_id = thread.thread_id
						AND thread_user_post.user_id = ' . $this->_getDb()->quote($fetchOptions['postCountUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS user_post_count';
			}
		}

		if (!empty($fetchOptions['permissionCombinationId']))
		{
			$selectFields .= ',
				permission.cache_value AS node_permission_cache';
			$joinTables .= '
				LEFT JOIN xf_permission_cache_content AS permission
					ON (permission.permission_combination_id = ' . $this->_getDb()->quote($fetchOptions['permissionCombinationId']) . '
						AND permission.content_type = \'node\'
						AND permission.content_id = thread.node_id)';
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables,
			'orderClause'  => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}

	/**
	 * Prepares a collection of thread fetching related conditions into an SQL clause
	 *
	 * @param array $conditions List of conditions
	 * @param array $fetchOptions Modifiable set of fetch options (may have joins pushed on to it)
	 *
	 * @return string SQL clause (at least 1=1)
	 */
	public function prepareThreadConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['thread_id_gt']))
		{
			$sqlConditions[] = 'thread.thread_id > ' . $db->quote($conditions['thread_id_gt']);
		}

		if (!empty($conditions['title']))
		{
			if (is_array($conditions['title']))
			{
				$sqlConditions[] = 'thread.title LIKE ' . XenForo_Db::quoteLike($conditions['title'][0], $conditions['title'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'thread.title LIKE ' . XenForo_Db::quoteLike($conditions['title'], 'lr', $db);
			}
		}

		if (!empty($conditions['forum_id']) && empty($conditions['node_id']))
		{
			$conditions['node_id'] = $conditions['forum_id'];
		}

		if (!empty($conditions['node_id']))
		{
			if (is_array($conditions['node_id']))
			{
				$sqlConditions[] = 'thread.node_id IN (' . $db->quote($conditions['node_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'thread.node_id = ' . $db->quote($conditions['node_id']);
			}
		}

		if (!empty($conditions['discussion_type']))
		{
			if (is_array($conditions['discussion_type']))
			{
				$sqlConditions[] = 'thread.discussion_type IN (' . $db->quote($conditions['discussion_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'thread.discussion_type = ' . $db->quote($conditions['discussion_type']);
			}
		}

		if (!empty($conditions['not_discussion_type']))
		{
			if (is_array($conditions['not_discussion_type']))
			{
				$sqlConditions[] = 'thread.discussion_type NOT IN (' . $db->quote($conditions['not_discussion_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'thread.discussion_type <> ' . $db->quote($conditions['not_discussion_type']);
			}
		}

		if (!empty($conditions['prefix_id']))
		{
			if (is_array($conditions['prefix_id']))
			{
				if (in_array(-1, $conditions['prefix_id'])) {
					$conditions['prefix_id'][] = 0;
				}
				$sqlConditions[] = 'thread.prefix_id IN (' . $db->quote($conditions['prefix_id']) . ')';
			}
			else if ($conditions['prefix_id'] == -1)
			{
				$sqlConditions[] = 'thread.prefix_id = 0';
			}
			else
			{
				$sqlConditions[] = 'thread.prefix_id = ' . $db->quote($conditions['prefix_id']);
			}
		}

		if (isset($conditions['sticky']))
		{
			$sqlConditions[] = 'thread.sticky = ' . ($conditions['sticky'] ? 1 : 0);
		}

		if (isset($conditions['discussion_open']))
		{
			$sqlConditions[] = 'thread.discussion_open = ' . ($conditions['discussion_open'] ? 1 : 0);
		}

		if (!empty($conditions['discussion_state']))
		{
			if (is_array($conditions['discussion_state']))
			{
				$sqlConditions[] = 'thread.discussion_state IN (' . $db->quote($conditions['discussion_state']) . ')';
			}
			else
			{
				$sqlConditions[] = 'thread.discussion_state = ' . $db->quote($conditions['discussion_state']);
			}
		}

		if (isset($conditions['deleted']) || isset($conditions['moderated']))
		{
			$sqlConditions[] = $this->prepareStateLimitFromConditions($conditions, 'thread', 'discussion_state');
		}

		if (!empty($conditions['last_post_date']) && is_array($conditions['last_post_date']))
		{
			$sqlConditions[] = $this->getCutOffCondition("thread.last_post_date", $conditions['last_post_date']);
		}

		if (!empty($conditions['post_date']) && is_array($conditions['post_date']))
		{
			$sqlConditions[] = $this->getCutOffCondition("thread.post_date", $conditions['post_date']);
		}

		if (!empty($conditions['reply_count']) && is_array($conditions['reply_count']))
		{
			$sqlConditions[] = $this->getCutOffCondition("thread.reply_count", $conditions['reply_count']);
		}

		if (!empty($conditions['first_post_likes']) && is_array($conditions['first_post_likes']))
		{
			$sqlConditions[] = $this->getCutOffCondition("thread.first_post_likes", $conditions['first_post_likes']);
		}

		if (!empty($conditions['view_count']) && is_array($conditions['view_count']))
		{
			$sqlConditions[] = $this->getCutOffCondition("thread.view_count", $conditions['view_count']);
		}

		// fetch threads only from forums with find_new = 1
		if (!empty($conditions['find_new']) && isset($fetchOptions['join']) && $fetchOptions['join'] & self::FETCH_FORUM_OPTIONS)
		{
			$sqlConditions[] = 'forum.find_new = 1';
		}

		// thread starter
		if (isset($conditions['user_id']))
		{
			if (is_array($conditions['user_id']))
			{
				$sqlConditions[] = 'thread.user_id IN (' . $db->quote($conditions['user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'thread.user_id = ' . $db->quote($conditions['user_id']);
			}
		}

		// watch limit
		if (!empty($conditions['watch_only']))
		{
			$parts = array();
			if (!empty($fetchOptions['forumWatchUserId']))
			{
				$parts[] = 'forum_watch.node_id IS NOT NULL';
			}
			if (!empty($fetchOptions['watchUserId']))
			{
				$parts[] = 'thread_watch.thread_id IS NOT NULL';
			}
			if (!$parts)
			{
				$sqlConditions[] = '0'; // no watch info - return nothing
			}
			else
			{
				$sqlConditions[] = '(' . implode(' OR ', $parts) . ')';
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}
	
	/**
	 * Gets the count of threads with the specified criteria.
	 *
	 * @param array $conditions Conditions to apply to the fetching
	 *
	 * @return integer
	 */
	public function countThreads(array $conditions)
	{
		$fetchOptions = array();
		$whereConditions = $this->prepareThreadConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareThreadFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_thread AS thread
			' . $sqlClauses['joinTables'] . '
			WHERE ' . $whereConditions . '
		');
	}
}