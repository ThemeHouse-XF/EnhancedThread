<?php

//######################## Enhanced Threads By ThemeHouse ###########################
abstract class ThemeHouse_EnhancedThread_Option_ForumChooser
{
	public static function renderSelect(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {

        return self::_render('th_helper_threads_forums', $view, $fieldPrefix, $preparedOption, $canEdit);
    }

    public static function getNodeOptions($selectedForum, $includeRoot = false, $filter = false)
    {
        $nodeModel = XenForo_Model::create('XenForo_Model_Node');

        $options = $nodeModel->getNodeOptionsArray(
            $nodeModel->getAllNodes(),
            $selectedForum,
            $includeRoot
        );

        if ($filter)
        {
            foreach ($options AS &$option)
            {
                if (!empty($option['node_type_id']) && $option['node_type_id'] != $filter)
                {
                    $option['disabled'] = 'disabled';
                }

                unset($option['node_type_id']);
            }
        }

        return $options;
    }
	
    protected static function _render($templateName, XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        $filter = isset($preparedOption['nodeFilter']) ? $preparedOption['nodeFilter'] : false;

        $preparedOption['formatParams'] = self::getNodeOptions(
            $preparedOption['option_value'],
            sprintf('(%s)', new XenForo_Phrase('unspecified')),
            $filter
        );

        return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
            $templateName, $view, $fieldPrefix, $preparedOption, $canEdit
        );
    }
}