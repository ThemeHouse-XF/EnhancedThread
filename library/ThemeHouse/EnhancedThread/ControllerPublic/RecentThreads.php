<?php

//######################## Enhanced Threads By ThemeHouse ###########################
class ThemeHouse_EnhancedThread_ControllerPublic_RecentThreads extends XenForo_ControllerPublic_Abstract
{
    public function actionIndex()
    {
        $page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));

        $threadsPerPage = max(1, XenForo_Application::get('options')->discussionsPerPage);

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
            'page' => $page,
            'perPage' => $threadsPerPage
        );

        $count = $this->_getRecentThreadsModel()->countThreads(
            $conditions, $fetchOptions
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

        $viewParams = array(
            'threadsPerPage' => $threadsPerPage,
            'totalThreads' => $count,
            'page' => $page
        );

        $viewParams['threads'] = $threads;

        return $this->responseView('ThemeHouse_EnhancedThread_ViewPublic_List', 'th_important_threads_page', $viewParams);
    }
	
	/**
	 * @return ThemeHouse_EnhancedThread_Model_RecentThreads
	 */
	protected function _getRecentThreadsModel()
	{
		return $this->getModelFromCache('ThemeHouse_EnhancedThread_Model_RecentThreads');
	}
}