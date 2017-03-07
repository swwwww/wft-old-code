<?php

namespace Www;

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

        $action = $matches->getParam('action');

        // Set the layout template
        $viewModel = $mvcEvent->getViewModel();
        $viewModel->mod = strtolower($route[2]);
        $viewModel->active = $action;
        $viewModel->setTemplate('layout_www/layout_www');

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
