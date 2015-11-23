<?php

//######################## Enhanced Threads By ThemeHouse ###########################
class ThemeHouse_EnhancedThread_Route_Prefix_RecentThreads implements XenForo_Route_Interface
{
    public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
    {
        return $router->getRouteMatch('ThemeHouse_EnhancedThread_ControllerPublic_RecentThreads', 'Index', 'forums');
    }
}