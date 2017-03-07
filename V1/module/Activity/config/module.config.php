<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

return array(
    'router' => array(
        'routes' => array(

            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /application/:controller/:action
            'activity' => array(
                'type' => 'Literal',
                'options' => array(
                    'route' => '/activity',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Activity\Controller',
                        'controller' => 'index',
                        'action' => 'index',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'default' => array(
                        'type' => 'Segment',
                        'options' => array(
                            'route' => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                // 'id'=>'[a-zA-Z]*[0-9=]*'
                            ),
                            'defaults' => array(),
                        ),
                    ),
                ),
            ),
        ),
    ),
    'service_manager' => array(
        'abstract_factories' => array(
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            'Zend\Log\LoggerAbstractServiceFactory',
        ),
        'aliases' => array(
            'translator' => 'MvcTranslator',
        ),
    ),

    'controllers' => array(
        'invokables' => array(
            'Activity\Controller\Index' => 'Activity\Controller\IndexController',
            'Activity\Controller\Manage' => 'Activity\Controller\ManageController',
            'Activity\Controller\HuiJu' => 'Activity\Controller\HuiJuController',
            'Activity\Controller\HuiJuAdmin' => 'Activity\Controller\HuiJuAdminController',
            'Activity\Controller\Pingxuan' => 'Activity\Controller\PingxuanController',
            'Activity\Controller\PingxuanAdmin' => 'Activity\Controller\PingxuanAdminController',
        ),
    ),
    'view_manager' => array(
        'display_not_found_reason' => true,

        'doctype' => 'HTML5',
//        'not_found_template' => 'error/404',
//        'exception_template' => 'error/index',
        'template_map' => array(
            // 'layout/layout' => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
//            'error/404' => __DIR__ . '/../view/error/404.phtml',
//            'error/index' => __DIR__ . '/../view/error/index.phtml',
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
);
