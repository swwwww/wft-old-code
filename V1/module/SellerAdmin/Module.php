<?php

namespace SellerAdmin;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;

class Module
{

    private $serviceManager;

    public function onBootstrap(MvcEvent $e)
    {
        $eventManager = $e->getApplication()->getEventManager();
        $this->serviceManager = $e->getApplication()->getServiceManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
        //添加验证
//        $application = $e->getParam('application');
//        $application->getEventManager()->attach('dispatch', array($this, 'validation'), 100);
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
                    __NAMESPACE__ => __DIR__ ,
                    'Deyi' => 'vendor/Deyi',
                ),
            ),
        );
    }
}
