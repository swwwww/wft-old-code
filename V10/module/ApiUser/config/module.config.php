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
            'user' => array(
                'type' => 'Literal',
                'options' => array(
                    'route' => '/user',
                    'defaults' => array(
                        '__NAMESPACE__' => 'ApiUser\Controller',
                        'controller' => 'Index',
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
    'translator' => array(
        'locale' => 'zh_CN',
        'translation_file_patterns' => array(
            array(
                'type' => 'gettext',
                'base_dir' => __DIR__ . '/../language',
                'pattern' => '%s.mo',
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'ApiUser\Controller\Index' => 'ApiUser\Controller\IndexController',
            'ApiUser\Controller\Login' => 'ApiUser\Controller\LoginController',
            'ApiUser\Controller\Link' => 'ApiUser\Controller\LinkController',
            'ApiUser\Controller\Info' => 'ApiUser\Controller\InfoController',
            'ApiUser\Controller\Phone' => 'ApiUser\Controller\PhoneController',
            'ApiUser\Controller\Associates' => 'ApiUser\Controller\AssociatesController',
            'ApiUser\Controller\Collect' => 'ApiUser\Controller\CollectController',
            'ApiUser\Controller\Message' => 'ApiUser\Controller\MessageController',
            'ApiUser\Controller\GroupBuy' => 'ApiUser\Controller\GroupBuyController',
            'ApiUser\Controller\Account' => 'ApiUser\Controller\AccountController',
            'ApiUser\Controller\OrderList' => 'ApiUser\Controller\OrderListController',
            'ApiUser\Controller\Sell' => 'ApiUser\Controller\SellController',
        ),
    ),
    'view_manager' => array(
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),
);
