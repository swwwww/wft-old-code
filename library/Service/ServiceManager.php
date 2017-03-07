<?php
namespace library\Service;

class ServiceManager
{

    /**
     * @var \Zend\ServiceManager\ServiceManager
     */
    private static $ServiceManager;


    /**
     * @param $name
     * @return mixed
     */
    static public function get($name)
    {
        return self::$ServiceManager->get($name);
    }

    /**
     * 设置服务管理器
     * @param $application
     */
    static public function setManager($application)
    {
        self::$ServiceManager = $application->getServiceManager();
    }


    /**
     * @param string $name
     * @return mixed
     */
    static public function getConfig($name = '')
    {
        return self::get('config')[$name];
    }
}