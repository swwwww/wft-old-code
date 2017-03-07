<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

return [
    'router' => [
        'routes' => [

            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /application/:controller/:action
            'WebActivity' => [
                'type' => 'Literal',
                'options' => [
                    'route' => '/webinvite',
                    'defaults' => [
                        '__NAMESPACE__' => 'WebInvite\Controller',
                        'controller' => 'index',
                        'action' => 'index'
                    ]
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'default' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/[:controller[/:action]]',
                            'constraints' => [
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                // 'id'=>'[a-zA-Z]*[0-9=]*'
                            ],
                            'defaults' => []
                        ]
                    ]
                ]
            ]
        ]
    ],

    'service_manager' => [
        'abstract_factories' => [
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            'Zend\Log\LoggerAbstractServiceFactory'
        ],
        'aliases' => [
            'translator' => 'MvcTranslator'
        ]
    ],

    'controllers' => [
        'invokables' => [
            'WebInvite\Controller\Index' => 'WebInvite\Controller\IndexController',
        ]
    ],

    'view_manager' => [
        'display_not_found_reason' => true,

        'doctype' => 'HTML5',
        //'not_found_template' => 'error/404',
        //'exception_template' => 'error/index',
        'template_map' => [
            //'layout/layout' => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            //'error/404' => __DIR__ . '/../view/error/404.phtml',
            //'error/index' => __DIR__ . '/../view/error/index.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view'
        ]
    ],

    'service_manager' => [
        'invokables' => [
            'my-foo' => 'MyModule\Foo\Bar'
        ],
    ],
];
