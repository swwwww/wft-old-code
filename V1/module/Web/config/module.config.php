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
            'web' => array(
                'type' => 'Literal',
                'options' => array(
                    'route' => '/web',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Web\Controller',
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
//                                 'id'=>'[a-zA-Z]*[0-9=]*'
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
            'Web\Controller\Index' => 'Web\Controller\IndexController',
            'Web\Controller\Coupon' => 'Web\Controller\CouponController',
            'Web\Controller\Notify' => 'Web\Controller\NotifyController',
            'Web\Controller\Place' =>  'Web\Controller\PlaceController',
            'Web\Controller\Tag' =>  'Web\Controller\TagController',
            'Web\Controller\Organizer' =>  'Web\Controller\OrganizerController',
            'Web\Controller\WapPay' =>  'Web\Controller\WapPayController',
            'Web\Controller\WapPayPage' =>  'Web\Controller\WapPayPageController',
            'Web\Controller\GeTui' =>  'Web\Controller\GeTuiController',
            'Web\Controller\Circle' =>  'Web\Controller\CircleController',
            'Web\Controller\Redirect' =>  'Web\Controller\RedirectController',
            'Web\Controller\H5' =>  'Web\Controller\H5Controller',
            'Web\Controller\Comment' =>  'Web\Controller\CommentController',
            'Web\Controller\Search' =>  'Web\Controller\SearchController',
            'Web\Controller\Generalize' =>  'Web\Controller\GeneralizeController',
            'Web\Controller\Ground' =>  'Web\Controller\GroundController',
            'Web\Controller\Travel' =>  'Web\Controller\TravelController',
            'Web\Controller\Kidsplay' =>  'Web\Controller\KidsplayController',
            'Web\Controller\User' =>  'Web\Controller\UserController',
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
