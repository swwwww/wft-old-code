<?php

namespace Activity;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        $eventManager = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        $application = $e->getParam('application');
        $application->getEventManager()->attach('dispatch', array($this, 'validation'), 100);
    }

    public function validation($mvcEvent)
    {
        $matches = $mvcEvent->getRouteMatch();
        $controller = $matches->getParam('controller');
        $route = explode('\\', $controller);
        if ($route[0] !== __NAMESPACE__) {
            // not a controller from this module
            return false;
        }

        // Set the layout template
        $viewModel = $mvcEvent->getViewModel();
        $viewModel->setTemplate('layoutweb/layout');
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
