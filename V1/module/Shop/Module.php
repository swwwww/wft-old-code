<?php

namespace Shop;

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
        $viewModel->setTemplate('layoutmanager/layout');

        //验证
        if ($_SERVER['REQUEST_URI'] != '/shop/index/login') { //排除登陆界面
            if ($_COOKIE['user']) {
                $hash_data = json_encode(array('user' => $_COOKIE['user'], 'id' => (int)$_COOKIE['id'], 'group' => (int)$_COOKIE['group']));
                $token = hash_hmac('sha1', $hash_data, $this->serviceManager->get('config')['token_key']);

                if ($_COOKIE['group'] == 2 && $token == $_COOKIE['token']) {
                    return true;
                }
            }
            header('Location: /shop/index/login');
            exit;
        }
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
