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
            'coupon' => array(
                'type' => 'Literal',
                'options' => array(
                    'route' => '/coupon',
                    'defaults' => array(
                        '__NAMESPACE__' => 'ApiCoupon\Controller',
                        'controller' => 'Home',
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
            'ApiCoupon\Controller\Home' => 'ApiCoupon\Controller\HomeController',
            'ApiCoupon\Controller\List' => 'ApiCoupon\Controller\ListController',
            'ApiCoupon\Controller\Info' => 'ApiCoupon\Controller\InfoController',
            'ApiCoupon\Controller\Mylist' => 'ApiCoupon\Controller\MylistController',
            'ApiCoupon\Controller\Code' => 'ApiCoupon\Controller\CodeController',
            'ApiCoupon\Controller\Weather' => 'ApiCoupon\Controller\WeatherController',
            'ApiCoupon\Controller\Share' => 'ApiCoupon\Controller\ShareController',
        ),
    ),
    'view_manager' => array(
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),
);
