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
            'admin' => array(
                'type' => 'Literal',
                'options' => array(
                    'route' => '/wftadlogin',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Admin\Controller',
                        'controller' => 'Statistics',
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
            'Admin\Controller\Basis' => 'Admin\Controller\BasisController',
            'Admin\Controller\Index' => 'Admin\Controller\IndexController',
            'Admin\Controller\User' => 'Admin\Controller\UserController',
            'Admin\Controller\Order' => 'Admin\Controller\OrderController',
            'Admin\Controller\Seller' => 'Admin\Controller\SellerController',
            'Admin\Controller\Coupons' => 'Admin\Controller\CouponsController',
            'Admin\Controller\Backinfo' => 'Admin\Controller\BackinfoController',
            'Admin\Controller\Setting' => 'Admin\Controller\SettingController',
            'Admin\Controller\Activity' => 'Admin\Controller\ActivityController',
            'Admin\Controller\Post' => 'Admin\Controller\PostController',
            'Admin\Controller\News' => 'Admin\Controller\NewsController',
            'Admin\Controller\Page' => 'Admin\Controller\PageController',
            'Admin\Controller\Place' => 'Admin\Controller\PlaceController',
           /* 'Admin\Controller\FirstPage' => 'Admin\Controller\FirstPageController',*/
            'Admin\Controller\Label' => 'Admin\Controller\LabelController',
            'Admin\Controller\Organizer' => 'Admin\Controller\OrganizerController',
            'Admin\Controller\Game' => 'Admin\Controller\GameController',
            'Admin\Controller\WeiXin' => 'Admin\Controller\WeiXinController',
            'Admin\Controller\GeTui' => 'Admin\Controller\GeTuiController',
            'Admin\Controller\OutLook' => 'Admin\Controller\OutLookController',
            'Admin\Controller\Circle' => 'Admin\Controller\CircleController',
            'Admin\Controller\Word' => 'Admin\Controller\WordController',
            'Admin\Controller\Code' => 'Admin\Controller\CodeController',
            'Admin\Controller\Tag' => 'Admin\Controller\TagController',
            'Admin\Controller\Nearby' => 'Admin\Controller\NearbyController',
            'Admin\Controller\First' => 'Admin\Controller\FirstController',
            'Admin\Controller\Account' => 'Admin\Controller\AccountController',
            'Admin\Controller\AuthAccess' => 'Admin\Controller\AuthAccessController',
            'Admin\Controller\AuthGroup' => 'Admin\Controller\AuthGroupController',
            'Admin\Controller\AuthMenu' => 'Admin\Controller\AuthMenuController',
            'Admin\Controller\Qualify' => 'Admin\Controller\QualifyController',
            'Admin\Controller\CashCoupon' => 'Admin\Controller\CashCouponController',
            'Admin\Controller\CashCouponUser' => 'Admin\Controller\CashCouponUserController',
            'Admin\Controller\CashCouponGood' => 'Admin\Controller\CashCouponGoodController',
            'Admin\Controller\CashCouponCity' => 'Admin\Controller\CashCouponCityController',
            'Admin\Controller\Integral' => 'Admin\Controller\IntegralController',
            'Admin\Controller\IntegralUser' => 'Admin\Controller\IntegralUserController',
            'Admin\Controller\City' => 'Admin\Controller\CityController',
            'Admin\Controller\Strategy' => 'Admin\Controller\StrategyController',
            'Admin\Controller\Welfare' => 'Admin\Controller\WelfareController',
            'Admin\Controller\Consult' => 'Admin\Controller\ConsultController',
            'Admin\Controller\Comment' => 'Admin\Controller\CommentController',
            'Admin\Controller\Invite' => 'Admin\Controller\InviteController',
            'Admin\Controller\Contract' => 'Admin\Controller\ContractController',
            'Admin\Controller\Good' => 'Admin\Controller\GoodController',
            'Admin\Controller\People' => 'Admin\Controller\PeopleController',
            'Admin\Controller\BackCash' => 'Admin\Controller\BackCashController',
            'Admin\Controller\Finance' => 'Admin\Controller\FinanceController',
            'Admin\Controller\FundsCheck' => 'Admin\Controller\FundsCheckController',
            'Admin\Controller\Charge' => 'Admin\Controller\ChargeController',
            'Admin\Controller\Clearing' => 'Admin\Controller\ClearingController',
            'Admin\Controller\Approve' => 'Admin\Controller\ApproveController',
            'Admin\Controller\Award' => 'Admin\Controller\AwardController',
            'Admin\Controller\Pact' => 'Admin\Controller\PactController',
            'Admin\Controller\Business' => 'Admin\Controller\BusinessController',
            'Admin\Controller\OrganizerRecord' => 'Admin\Controller\OrganizerRecordController',
            'Admin\Controller\PrivateParty' => 'Admin\Controller\PrivatePartyController',
            'Admin\Controller\Insurance' => 'Admin\Controller\InsuranceController',
            'Admin\Controller\Statistics' => 'Admin\Controller\StatisticsController',
            'Admin\Controller\Zyb' => 'Admin\Controller\ZybController',
            'Admin\Controller\Excercise' => 'Admin\Controller\ExcerciseController',
            'Admin\Controller\Yards' => 'Admin\Controller\YardsController',
            'Admin\Controller\Inventory' => 'Admin\Controller\InventoryController', //库存管理
            'Admin\Controller\YardsCode' => 'Admin\Controller\YardsCodeController', //活动受理退款
            'Admin\Controller\Distribution' => 'Admin\Controller\DistributionController', //活动受理退款
            'Admin\Controller\Update' => 'Admin\Controller\UpdateController', //活动受理退款
            'Admin\Controller\File' => 'Admin\Controller\FileController', //新编辑器
            'Admin\Controller\H5statistics' => 'Admin\Controller\H5statisticsController', //新编辑器
        ),
    ),
    'view_manager' => array(
        'display_not_found_reason' => true,

        'doctype' => 'HTML5',
        'not_found_template' => 'error/404',
        'exception_template' => 'error/index',
        'template_map' => array(
            //  'layout/layout' => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404' => __DIR__ . '/../view/error/404.phtml',
            'error/index' => __DIR__ . '/../view/error/index.phtml',
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
);
