<?php

//######################## Enhanced Threads By ThemeHouse ###########################
class ThemeHouse_EnhancedThread_ControllerPublic_Forum extends XFCP_ThemeHouse_EnhancedThread_ControllerPublic_Forum
{
	public function actionIndex()
    {
        //Get parent.
	    $parent = parent::actionIndex();
		
		//Return parent if this is a non View response. 
		if (!$parent instanceof XenForo_ControllerResponse_View)
		{
			return $parent;
		}
		
		//Return if rss response.
		if ($this->_routeMatch->getResponseType() == 'rss')	
		{
			return $parent;
		}
		
		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);
		
		if ($forumId || $forumName)
		{
			return $parent;
		}
		
		$options = XenForo_Application::getOptions();
		
		$defaultOrder = XenForo_Application::get('options')->threadsSortOrder;
		$defaultOrderDirection = XenForo_Application::get('options')->threadsSortOrderDir;
		
		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection));
		
		$conditions = array(
            'node_id' => (!empty($options->exclFids)) ? $options->exclFids : null,
            'reply_count' => array('>=', $options->exludedReplies),
            'view_count' => array('>=', $options->exludedViews),
            'not_discussion_type' => 'redirect'
        );
		
		$fetchOptions = array(
            'join' => ThemeHouse_EnhancedThread_Model_RecentThreads::FETCH_USER | ThemeHouse_EnhancedThread_Model_RecentThreads::FETCH_FORUM | ThemeHouse_EnhancedThread_Model_RecentThreads::FETCH_DELETION_LOG | ThemeHouse_EnhancedThread_Model_RecentThreads::FETCH_FORUM_OPTIONS,
			'order' => $order,
			'direction' => $orderDirection,
            'limit' => $options->limitThreads
        );

		$threads = $this->_getRecentThreadsModel()->getThreads($conditions, $fetchOptions);
		
		$threadModel = $this->getModelFromCache('XenForo_Model_Thread');
		
		foreach ($threads AS $threadId => &$thread)
        {
            $thread = $threadModel->prepareThread($thread, $thread);
            if (!$threadModel->canViewThread($thread, $thread))
            {
                unset($threads[$threadId]);
            }
        }
		
		$parent->params['threads'] = $threads;
	    
		return $parent;
		
	}
	
	/**
	 * @return ThemeHouse_EnhancedThread_Model_RecentThreads
	 */
	protected function _getRecentThreadsModel()
	{
		return $this->getModelFromCache('ThemeHouse_EnhancedThread_Model_RecentThreads');
	}
}