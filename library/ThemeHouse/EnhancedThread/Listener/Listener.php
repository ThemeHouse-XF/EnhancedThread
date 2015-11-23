<?php

//######################## Enhanced Threads By ThemeHouse ###########################
class ThemeHouse_EnhancedThread_Listener_Listener
{
    //Extends XenForo_ControllerPublic_Forum class.
    public static function controller($class, &$extend)
    {
        if ($class == 'XenForo_ControllerPublic_Forum')
        {
            $extend[] = 'ThemeHouse_EnhancedThread_ControllerPublic_Forum';
        }
    }
}