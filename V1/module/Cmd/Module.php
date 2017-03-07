<?php

namespace Cmd;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\Console\Adapter\AdapterInterface as Console;

class Module implements ConsoleUsageProviderInterface
{

    //对应功能注册
    public function getConsoleUsage(Console $console)
    {
        return array(
            'out' => 'Export User Data',//导出用户订单数据
            'autoNewsStatus' => 'autoNewsStatus',//导出用户订单数据
            'solr' => 'solr',//搜索数据更新
            'updateActivity' => 'updateActivity',//搜索数据更新
            'tmp' => 'tmp',
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__,
                    'Deyi' => 'vendor/Deyi',
                ),
            ),
        );
    }
}
