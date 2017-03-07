<?php

return array(

    'console' => array(
        'router' => array(
            'routes' => array(

                'out' => array(
                    'options' => array(
                        'route' => 'out',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'out',
                        ),
                    ),
                ),
                'autoNewsStatus' => array(
                    'options' => array(
                        'route' => 'autoNewsStatus',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'autoNewsStatus',
                        ),
                    ),
                ),
                'cash' => array(
                    'options' => array(
                        'route' => 'cash',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'cash',
                        ),
                    ),
                ),

                'solr' => array(
                    'options' => array(
                        'route' => 'solr',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'solr',
                        ),
                    ),
                ),
                'updateActivity' => array(
                    'options' => array(
                        'route' => 'updateActivity',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'updateActivity',
                        ),
                    ),
                ),

                'tmp' => array(
                    'options' => array(
                        'route' => 'tmp',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'tmp',
                        ),
                    ),
                ),


                'updateConsult' => array(
                    'options' => array(
                        'route' => 'updateConsult',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'updateConsult',
                        ),
                    ),
                ),

                'updateGoods' => array(
                    'options' => array(
                        'route' => 'updateGoods',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'updateGoods',
                        ),
                    ),
                ),


                'soldNumber' => array(
                    'options' => array(
                        'route' => 'soldNumber',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'soldNumber',
                        ),
                    ),
                ),

                'autoBalance' => array(
                    'options' => array(
                        'route' => 'autoBalance',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'autoBalance',
                        ),
                    ),
                ),
                'together' => array(
                    'options' => array(
                        'route' => 'together',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Stat',
                            'action' => 'together',
                        ),
                    ),
                ),
                'history' => array(
                    'options' => array(
                        'route' => 'history',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Stat',
                            'action' => 'history',
                        ),
                    ),
                ),
                'autoBackCode' => array(
                    'options' => array(
                        'route' => 'autoBackCode',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'autoBackCode',
                        ),
                    ),
                ),

                'verify' => array(
                    'options' => array(
                        'route' => 'verify',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Order',
                            'action' => 'verify',
                        ),
                    ),
                ),

                'dissolve' => array(
                    'options' => array(
                        'route' => 'dissolve',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Order',
                            'action' => 'dissolve',
                        ),
                    ),
                ),
                'autoNews' => array(
                    'options' => array(
                        'route' => 'autoNews',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Order',
                            'action' => 'autoNews',
                        ),
                    ),
                ),
                'autoHotalUseRemind' => array(
                    'options' => array(
                        'route' => 'autoHotalUseRemind',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Order',
                            'action' => 'autoHotalUseRemind',
                        ),
                    ),
                ),
                'virface' => array(
                    'options' => array(
                        'route' => 'virface',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'virface',
                        ),
                    ),
                ),
                'HandleOrder' => array(
                    'options' => array(
                        'route' => 'HandleOrder',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'AutoCheck',
                            'action' => 'HandleOrder',
                        ),
                    ),
                ),
                'autoCheck' => array(
                    'options' => array(
                        'route' => 'autoCheck',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'AutoCheck',
                            'action' => 'autoCheck',
                        ),
                    ),
                ),
                'updateUserCity' => array(
                    'options' => array(
                        'route' => 'updateUserCity',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'updateUserCity',
                        ),
                    ),
                ),
                'sendMeituanCoupon' => array(
                    'options' => array(
                        'route' => 'sendMeituanCoupon',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'sendMeituanCoupon',
                        ),
                    ),
                ),
                'recovery' => array(
                    'options' => array(
                        'route' => 'recovery',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Order',
                            'action' => 'recovery',
                        ),
                    ),
                ),
                'getui' => array(
                    'options' => array(
                        'route' => 'getui',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'getui',
                        ),
                    ),
                ),
                'autoBack' => array(
                    'options' => array(
                        'route' => 'autoBack',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Order',
                            'action' => 'autoBack',
                        ),
                    ),
                ),
                'disgroup' => array(
                    'options' => array(
                        'route' => 'disgroup',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Order',
                            'action' => 'disgroup',
                        ),
                    ),
                ),
                'autoRefund' => array(
                    'options' => array(
                        'route' => 'autoRefund',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'autoRefund',
                        ),
                    ),
                ),
                'updateAnalysis' => array(
                    'options' => array(
                        'route' => 'updateAnalysis',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'updateAnalysis',
                        ),
                    ),
                ),
                'updateUserAnalysis' => array(
                    'options' => array(
                        'route' => 'updateUserAnalysis',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'updateUserAnalysis',
                        ),
                    ),
                ),
                'closeOrder' => array(
                    'options' => array(
                        'route' => 'closeOrder',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'closeOrder',
                        ),
                    ),
                ),
                'vip' => array(
                    'options' => array(
                        'route' => 'vip',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Stat',
                            'action' => 'vip',
                        ),
                    ),
                ),
                'reconciliation' => array(
                    'options' => array(
                        'route' => 'reconciliation',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Order',
                            'action' => 'reconciliation',
                        ),
                    ),
                ),
                'checkAccount' => array(
                    'options' => array(
                        'route' => 'checkAccount',
                        'defaults' => array(
                            '__NAMESPACE__' => 'Cmd\Controller',
                            'controller' => 'Index',
                            'action' => 'checkAccount',
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
            'Cmd\Controller\Index' => 'Cmd\Controller\IndexController',
            'Cmd\Controller\Stat' => 'Cmd\Controller\StatController',
            'Cmd\Controller\Order' => 'Cmd\Controller\OrderController',
            'Cmd\Controller\AutoCheck' => 'Cmd\Controller\AutoCheckController',
        ),
    ),
);
