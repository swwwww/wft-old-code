<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use Application\Model\PlayAccountLogTable;
use Application\Model\PlayAccountTable;
use Application\Model\PlayActivityCouponTable;
use Application\Model\PlayActivityTagTable;
use Application\Model\PlayClickLogTable;
use Application\Model\PlayClientUpdateTable;
use Application\Model\PlayContractLinkPriceTable;
use Application\Model\PlayContractsTable;
use Application\Model\PlayCouponsLinkerTable;
use Application\Model\PlayExcerciseMeetingTable;
use Application\Model\PlayGoodCommentTable;
use Application\Model\PlayGroupBuyTable;
use Application\Model\PlayInventoryTable;
use Application\Model\PlayInviteContentTable;
use Application\Model\PlayAdminCashTable;
use Application\Model\PlayLikeTable;
use Application\Model\PlayOrderInfoGameTable;
use Application\Model\PlayOrderOtherDataTable;
use Application\Model\PlayOrganizerAccountTable;
use Application\Model\PlayOrganizerCodeLogTable;
use Application\Model\PlayOrganizerTable;
use Application\Model\PlayOrganizerGameTable;
use Application\Model\PlayPatchUpdateTable;
use Application\Model\PlayPostTable;
use Application\Model\PlayPrivatePartyTable;
use Application\Model\PlaySearchFormValueTable;
use Application\Model\PlaySearchLogTable;
use Application\Model\PlaySettingsTable;
use Application\Model\PlayUserAssociatesTable;
use Application\Model\PlayOrderInsureTable;
use Application\Model\PlayUserWeiXinTable;
use Application\Model\PlayWelfareTable;
use Application\Model\PrizeLogTable;
use Application\Model\PrizeTable;
use Application\Model\PrizeUserDataTable;
use Application\Model\SocialChatMsgTable;
use Application\Model\SocialCircleMsgPostTable;
use Application\Model\SocialCircleMsgTable;
use Application\Model\SocialCircleTable;
use Application\Model\SocialCircleUsersTable;
use Application\Model\SocialFriendTable;
use Deyi\Mcrypt;
use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;

use Application\Model\PlayAdminTable;
use Application\Model\PlayAttachTable;
use Application\Model\PlayAuthCodeTable;
use Application\Model\PlayCouponsTable;
use Application\Model\PlayMarketTable;
use Application\Model\PlayMarketSettingTable;
use Application\Model\PlayOrderInfoTable;
use Application\Model\PlayOrderActionTable;
use Application\Model\PlayRegionTable;
use Application\Model\PlayShopTable;
use Application\Model\PlayCashShareTable;
use Application\Model\PlayUserTable;
use Application\Model\PlayCouponCodeTable;
use Application\Model\PlayFeedbackTable;
use Application\Model\PlayUserLinkerTable;
use Application\Model\PlayShareTable;
use Application\Model\PlayActivityTable;
use Application\Model\PlayIndexBlockTable;
use Application\Model\PlayIndexNewTable;
use Application\Model\PlayNewsTable;
use Application\Model\PlayFocusMapTable;
use Application\Model\PlayLabelTable;
use Application\Model\PlayLabelLinkerTable;
use Application\Model\PlayGameInfoTable;
use Application\Model\PlayGameTimeTable;
use Application\Model\PlayGamePriceTable;
use Application\Model\PlayUserCollectTable;
use Application\Model\PlayUserMessageTable;
use Application\Model\PlayUserBabyTable;
use Application\Model\PlayWebActivityTable;
use Application\Model\PlayTagsTable;
use Application\Model\PlayTagsLinkTable;
use Application\Model\PlayWelfareIntegralTable;
use Application\Model\PlayIntegralTable;
use Application\Model\CashCouponCityTable;
use Application\Model\CashCouponGoodTable;
use Application\Model\CashCouponTable;
use Application\Model\CashCouponUserTable;
use Application\Model\IntegralTable;
use Application\Model\IntegralUserTable;
use Application\Model\StationTable;
use Application\Model\QualifyTable;
use Application\Model\PlayShopStrategyTable;
//add kylin
use Application\Model\InviteTokenTable;
use Application\Model\InviteRuleTable;
use Application\Model\InviteMemberTable;
use Application\Model\InviteRecieverAwardLogTable;
use Application\Model\InviteInviterAwardLogTable;

use Application\Model\PlayCityTable;
use Application\Model\PlayCodeUsedTable;
use Application\Model\PlayExcerciseBaseTable;
use Application\Model\PlayExcerciseCodeTable;
use Application\Model\PlayExcerciseEventTable;
use Application\Model\PlayExcercisePriceTable;
use Application\Model\PlayExcerciseScheduleTable;
use Application\Model\PlayExcerciseShopTable;

use Application\Model\PlayDistributionDetailTable;
use Application\Model\PlayDistributionLogTable;


class Module
{
    static public $serviceManager;
    public function onBootstrap(MvcEvent $e)
    {
        self::$serviceManager = $e->getApplication()->getServiceManager();

        $eventManager = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);


        /**
         * 捕获错误，写入文件
         */
        $sharedManager = $e->getApplication()->getEventManager()->getSharedManager();
        $sm = $e->getApplication()->getServiceManager();
        $sharedManager->attach('Zend\Mvc\Application', 'dispatch.error',
            function($e) use ($sm) {
                if ($e->getParam('exception')){
                    $ex = $e->getParam('exception');
                    do {
                        $sm->get('Logger')->crit(
                            sprintf(
                                "%s:%d %s (%d) [%s]",
                                $ex->getFile(),
                                $ex->getLine(),
                                $ex->getMessage(),
                                $ex->getCode(),
                                get_class($ex)
                            ) . $ex->getTraceAsString() . "\n"
                        );
                    } while ($ex = $ex->getPrevious());
                }
            }
        );

        //记录访问日志
        if (ACCESSLOG){
            $e->getApplication()->getEventManager()->attach('dispatch', array($this, 'accessLog'), -10);
        }
    }




    public function accessLog($mvcEvent)
    {
        $matches = $mvcEvent->getRouteMatch();
        $controller = $matches->getParam('controller');
        $route = explode('\\', $controller);

        $m = new \MongoClient('mongodb://127.0.0.1:27017');
        $mongoDB = $m->wft_accesslog;
        //解析请求的参数
        parse_str($_SERVER['QUERY_STRING'], $query_array);
        $c_info = $this->getClientinfo();

        $encode_p=false;
        if(isset($_POST['p'])){
            $encryption = new Mcrypt();
            $decrypt_p = $encryption->decrypt($_POST['p']);
            $encode_p=json_decode($decrypt_p);
        }
//        session_start();
        $mongoDB->log_data->insert(array(
            'm' => $route[0],
            'c' => $route[1],
            'a' => $route[2],
            'url' => $_SERVER['REQUEST_URI'],  // /cashcoupon/index/nindex
            'ip' => $_SERVER['REMOTE_ADDR'],
            'query_array' => $query_array, // a=xx
            'post_array'=>$_POST,
            'cookie' => $_COOKIE,
            'session' => array(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:'',
            'dateline' => time(),
            'client' => $c_info['client'],
            'client_ver' => $c_info['ver'],
            'api_ver'=>$c_info['api_ver'],
            'decode_p'=>$encode_p?:array(),
            'run_time'=>round(microtime(true)-RUN_T1,3),
            'city'=>isset($_SERVER['HTTP_CITY']) ? urldecode($_SERVER['HTTP_CITY']) : 0
        ));
    }
    //获取客户端信息  ios是自定义的useragent
    public function getClientinfo()
    {
        $http_user_agent=isset($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:'';
        $ios_user_agent=isset($_SERVER['HTTP_USERAGENT'])?$_SERVER['HTTP_USERAGENT']:'';

        //ios android weixin other...
        $res = array('client' => 0, 'ver' => 0,'api_ver'=>0);
//        if ($ios_user_agent) {
//            $http_user_agent = $ios_user_agent;
//        }

        if(strpos($ios_user_agent, 'client')!==false){
            $http_user_agent = $ios_user_agent;
        }

        if(strpos($http_user_agent, 'wft')!==false){
            //ios or android
            preg_match('/wft\/(android|ios)\/client\/(.*)$/', $http_user_agent, $matches);    //正则匹配  _all所有
            $res = array('client' => $matches[1], 'ver' => $matches[2],'api_ver'=>(int)$_SERVER['HTTP_VER']);
        }elseif(strpos($http_user_agent, 'MicroMessenger')!==false){
            //微信
            preg_match('/MicroMessenger\/(.*?)\s/', $http_user_agent, $matches);    //正则匹配  _all所有
            $res = array('client' => 'weixin', 'ver' => isset($matches[2])?$matches[2]:'','api_ver'=>(int)$_SERVER['HTTP_VER']);
        }else{
            //其他浏览器
        }
        return $res;
    }


    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(

                'Logger' => function($sm){
                    $logger = new \Zend\Log\Logger;
                    $writer = new \Zend\Log\Writer\Stream('./log/'.date('Y-m-d').'-error.log');
                    $logger->addWriter($writer);
                    return $logger;
                },

                'Application/Module/PlayAdminTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_admin', $Adapter);
                    return new PlayAdminTable($table);
                },

                'Application/Module/PlayPrivatePartyTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_private_party', $Adapter);
                    return new PlayPrivatePartyTable($table);
                },

                'Application/Module/PlayGroupBuyTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_group_buy', $Adapter);
                    return new PlayGroupBuyTable($table);
                },

                'Application/Module/PlayInviteContentTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_invite_content', $Adapter);
                    return new PlayInviteContentTable($table);
                },


                'Application/Module/PlaySettingsTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_settings', $Adapter);
                    return new PlaySettingsTable($table);
                },
                'Application/Module/PlayCashShareTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_cash_share', $Adapter);
                    return new PlayCashShareTable($table);
                },
                'Application/Module/PlayUserWeiXinTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_user_weixin', $Adapter);
                    return new PlayUserWeiXinTable($table);
                },
                'Application/Module/PrizeTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_prize', $Adapter);
                    return new PrizeTable($table);
                },
                'Application/Module/PlayShopStrategyTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_shop_strategy', $Adapter);
                    return new PlayShopStrategyTable($table);
                },
                'Application/Module/PrizeUserDataTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_Prize_userdata', $Adapter);
                    return new PrizeUserDataTable($table);
                },
                'Application/Module/PlaySearchLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_search_log', $Adapter);
                    return new PlaySearchLogTable($table);
                },

                'Application/Module/PlaySearchFormValueTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_search_form_value', $Adapter);
                    return new PlaySearchFormValueTable($table);
                },

                'Application/Module/PlayClickLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_click_log', $Adapter);
                    return new PlayClickLogTable($table);
                },

                'Application/Module/PrizeLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_prize_log', $Adapter);
                    return new PrizeLogTable($table);
                },

                'Application/Module/PlayPostTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_post', $Adapter);
                    return new PlayPostTable($table);
                },

                'Application/Module/PlayLikeTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_like', $Adapter);
                    return new PlayLikeTable($table);
                },


                'Application/Module/PlayCouponsLinkerTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_coupons_linker', $Adapter);
                    return new PlayCouponsLinkerTable($table);
                },

                'Application/Module/PlayUserAssociatesTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_user_associates', $Adapter);
                    return new PlayUserAssociatesTable($table);
                },

		'Application/Module/PlayOrderInsureTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_order_insure', $Adapter);
                    return new PlayOrderInsureTable($table);
                },

                'Application/Module/PlayUserLinkerTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_user_linker', $Adapter);
                    return new PlayUserLinkerTable($table);
                },

                'Application/Module/PlayAuthCodeTable' => function ($sm) {

                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_auth_code', $Adapter);
                    return new PlayAuthCodeTable($table);
                },

                'Application/Module/PlayUserTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_user', $Adapter);
                    return new PlayUserTable($table);
                },
                'Application/Module/PlayOrderInfoTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_order_info', $Adapter);
                    return new PlayOrderInfoTable($table);
                },
                'Application/Module/PlayOrderActionTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_order_action', $Adapter);
                    return new PlayOrderActionTable($table);
                },

                'Application/Module/PlayCouponCodeTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_coupon_code', $Adapter);
                    return new PlayCouponCodeTable($table);
                },

                'Application/Module/PlayActivityCouponTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_activity_coupon', $Adapter);
                    return new PlayActivityCouponTable($table);
                },
                'Application/Module/PlayFeedbackTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_feedback', $Adapter);
                    return new PlayFeedbackTable($table);
                },
                'Application/Module/PlayMarketTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_market', $Adapter);
                    return new PlayMarketTable($table);
                },
                'Application/Module/PlayMarketSettingTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_market_setting', $Adapter);
                    return new PlayMarketSettingTable($table);
                },
                'Application/Module/PlayShopTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_shop', $Adapter);
                    return new PlayShopTable($table);
                },
                'Application/Module/PlayRegionTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_region', $Adapter);
                    return new PlayRegionTable($table);
                },
                'Application/Module/PlayShareTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_share', $Adapter);
                    return new PlayShareTable($table);
                },
                'Application/Module/PlayCouponsTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_coupons', $Adapter);
                    return new PlayCouponsTable($table);
                },
                'Application/Module/PlayAttachTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_attach', $Adapter);
                    return new PlayAttachTable($table);
                },
                'Application/Module/PlayCouponLinkerTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_coupons_linker', $Adapter);
                    return new PlayCouponLinkerTable($table);
                },
                'Application/Module/QualifyTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_qualify_coupon', $Adapter);
                    return new QualifyTable($table);
                },
                'Application/Module/PlayActivityTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_activity', $Adapter);
                    return new PlayActivityTable($table);
                },
                'Application/Module/PlayActivityTagTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_activity_tag', $Adapter);
                    return new PlayActivityTagTable($table);
                },
                'Application/Module/PlayIndexBlockTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_index_block', $Adapter);
                    return new PlayIndexBlockTable($table);
                },
                'Application/Module/PlayIndexNewTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_index_new', $Adapter);
                    return new PlayIndexNewTable($table);
                },
                'Application/Module/PlayNewsTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_news', $Adapter);
                    return new PlayNewsTable($table);
                },
                'Application/Module/PlayFocusMapTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_focus_map', $Adapter);
                    return new PlayFocusMapTable($table);
                },
                'Application/Module/PlayLabelTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_label', $Adapter);
                    return new PlayLabelTable($table);
                },
                'Application/Module/PlayAdminCashTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_admin_cash', $Adapter);
                    return new PlayAdminCashTable($table);
                },
                'Application/Module/PlayLabelLinkerTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_label_linker', $Adapter);
                    return new PlayLabelLinkerTable($table);
                },
                'Application/Module/PlayOrganizerTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_organizer', $Adapter);
                    return new PlayOrganizerTable($table);
                },
                'Application/Module/PlayOrganizerGameTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_organizer_game', $Adapter);
                    return new PlayOrganizerGameTable($table);
                },
                'Application/Module/PlayGameInfoTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_game_info', $Adapter);
                    return new PlayGameInfoTable($table);
                },
                'Application/Module/PlayGameTimeTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_game_time', $Adapter);
                    return new PlayGameTimeTable($table);
                },
                'Application/Module/PlayWelfareTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_welfare', $Adapter);
                    return new PlayWelfareTable($table);
                },
                'Application/Module/PlayGamePriceTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_game_price', $Adapter);
                    return new PlayGamePriceTable($table);
                },
                'Application/Module/PlayOrderInfoGameTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_order_info_game', $Adapter);
                    return new PlayOrderInfoGameTable($table);
                },
                'Application/Module/PlayUserCollectTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_user_collect', $Adapter);
                    return new PlayUserCollectTable($table);
                },
                'Application/Module/PlayUserMessageTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_user_message', $Adapter);
                    return new PlayUserMessageTable($table);
                },
                'Application/Module/PlayUserBabyTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_user_baby', $Adapter);
                    return new PlayUserBabyTable($table);
                },
                'Application/Module/SocialCircleUsersTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('social_circle_users', $Adapter);
                    return new SocialCircleUsersTable($table);
                },
                'Application/Module/SocialCircleTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('social_circle', $Adapter);
                    return new SocialCircleTable($table);
                },
                'Application/Module/SocialCircleMsgTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('social_circle_msg', $Adapter);
                    return new SocialCircleMsgTable($table);
                },
                'Application/Module/SocialCircleMsgPostTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('social_circle_msg_post', $Adapter);
                    return new SocialCircleMsgPostTable($table);
                },
                'Application/Module/SocialFriendsTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('social_friends', $Adapter);
                    return new SocialFriendTable($table);
                },
                'Application/Module/SocialChatMsgTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('social_chat_msg', $Adapter);
                    return new SocialChatMsgTable($table);
                },
                'Application/Module/PlayWebActivityTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_web_activity', $Adapter);
                    return new PlayWebActivityTable($table);
                },

                'Application/Module/PlayAccountTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table= new TableGateway('play_account', $Adapter);
                    return new PlayAccountTable($table);
                },

                'Application/Module/PlayAccountLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table= new TableGateway('play_account_log', $Adapter);
                    return new PlayAccountLogTable($table);
                },

                'Application/Module/PlayTagsTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table= new TableGateway('play_tags', $Adapter);
                    return new PlayTagsTable($table);
                },

                'Application/Module/PlayTagsLinkTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table= new TableGateway('play_tags_link', $Adapter);
                    return new PlayTagsLinkTable($table);
                },

                'Application/Module/PlayWelfareIntegralTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table= new TableGateway('play_welfare_integral', $Adapter);
                    return new PlayWelfareIntegralTable($table);
                },
                'Application/Module/PlayWelfareTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_welfare', $Adapter);
                    return new PlayWelfareTable($table);
                },
                'Application/Module/PlayIntegralTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_integral', $Adapter);
                    return new PlayIntegralTable($table);
                },

                'Application/Module/CashCouponCityTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_cashcoupon_city', $Adapter);
                    return new CashCouponCityTable($table);
                },

                'Application/Module/PlayGoodCommentTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_good_comment', $Adapter);
                    return new PlayGoodCommentTable($table);
                },

                'Application/Module/CashCouponGoodTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_cashcoupon_good_link', $Adapter);
                    return new CashCouponGoodTable($table);
                },

                'Application/Module/CashCouponUserTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_cashcoupon_user_link', $Adapter);
                    return new CashCouponUserTable($table);
                },
                'Application/Module/PlayOrderOtherDataTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_order_otherdata', $Adapter);
                    return new PlayOrderOtherDataTable($table);
                },
                'Application/Module/CashCouponTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_cash_coupon', $Adapter);
                    return new CashCouponTable($table);
                },

                'Application/Module/IntegralUserTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_integral_user', $Adapter);
                    return new IntegralUserTable($table);
                },

                'Application/Module/StationTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_station', $Adapter);
                    return new StationTable($table);
                },


                'Application/Module/InviteTokenTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('invite_token', $Adapter);
                    return new InviteTokenTable($table);
                },

                'Application/Module/InviteRule' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('invite_rule', $Adapter);
                    return new InviteRuleTable($table);
                },

                'Application/Module/InviteMember' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('invite_member', $Adapter);
                    return new InviteMemberTable($table);
                },

                'Application/Module/InviteRecieverAwardLog' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('invite_reciever_award_log', $Adapter);
                    return new InviteRecieverAwardLogTable($table);
                },

                'Application/Module/InviteInviterAwardLog' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('invite_inviter_award_log', $Adapter);
                    return new InviteInviterAwardLogTable($table);
                },

                'Application/Module/PlayCityTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_city', $Adapter);
                    return new PlayCityTable($table);
                },

                'Application/Module/PlayCodeUsedTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_code_used', $Adapter);
                    return new PlayCodeUsedTable($table);
                },

                //v3.3
                'Application/Module/PlayExcerciseBaseTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_excercise_base', $Adapter);
                    return new PlayExcerciseBaseTable($table);
                },

                'Application/Module/PlayExcerciseEventTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_excercise_event', $Adapter);
                    return new PlayExcerciseEventTable($table);
                },

                'Application/Module/PlayExcerciseCodeTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_excercise_code', $Adapter);
                    return new PlayExcerciseCodeTable($table);
                },

                'Application/Module/PlayExcercisePriceTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_excercise_price', $Adapter);
                    return new PlayExcercisePriceTable($table);
                },

                'Application/Module/PlayExcerciseScheduleTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_excercise_schedule', $Adapter);
                    return new PlayExcerciseScheduleTable($table);
                },

                'Application/Module/PlayExcerciseShopTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_excercise_shop', $Adapter);
                    return new PlayExcerciseShopTable($table);
                },
                'Application/Module/PlayExcerciseMeetingTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_excercise_meeting', $Adapter);
                    return new PlayExcerciseMeetingTable($table);
                },
                'Application/Module/PlayDistributionDetailTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_distribution_detail', $Adapter);
                    return new PlayDistributionDetailTable($table);
                },
                'Application/Module/PlayDistributionLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_distribution_log', $Adapter);
                    return new PlayDistributionLogTable($table);
                },
                'Application/Module/PlayClientUpdateTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_client_update', $Adapter);
                    return new PlayClientUpdateTable($table);
                },
                'Application/Module/PlayPatchUpdateTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_patch_update', $Adapter);
                    return new PlayPatchUpdateTable($table);
                },
                //add qinyuan
                'Application/Module/PlayContractsTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_contracts', $Adapter);
                    return new PlayContractsTable($table);
                },
                'Application/Module/PlayOrganizerAccountTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_organizer_account', $Adapter);
                    return new PlayOrganizerAccountTable($table);
                },
                'Application/Module/PlayInventoryTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_inventory', $Adapter);
                    return new PlayInventoryTable($table);
                },
                'Application/Module/PlayContractLinkPriceTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_contract_link_price', $Adapter);
                    return new PlayContractLinkPriceTable($table);
                },
                'Application/Module/PlayOrganizerCodeLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_organizer_code_log', $Adapter);
                    return new PlayOrganizerCodeLogTable($table);
                },

            )
        );

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
