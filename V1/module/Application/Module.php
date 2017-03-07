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
use Application\Model\PlayClientUpdateTable;
use Application\Model\PlayContractActionTable;
use Application\Model\PlayContractsLinkTable;
use Application\Model\PlayContractsPriceTable;
use Application\Model\PlayContractsTable;
use Application\Model\PlayExcerciseBaseTable;
use Application\Model\PlayExcerciseMeetingTable;
use Application\Model\PlayExcerciseCodeTable;
use Application\Model\PlayExcerciseEventTable;
use Application\Model\PlayExcercisePriceTable;
use Application\Model\PlayExcerciseScheduleTable;
use Application\Model\PlayExcerciseShopTable;
use Application\Model\PlayOrderInsureTable;
use Application\Model\PlayPatchUpdateTable;
use Application\Model\PlayUserAssociatesTable;
use Deyi\Mcrypt;
use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;

//add kylin
use Application\Model\AppGameCouponAddscorelogTable;
use Application\Model\InviteTokenTable;
use Application\Model\InviteRuleTable;
use Application\Model\InviteRuleLogTable;
use Application\Model\InviteMemberTable;
use Application\Model\InviteRecieverAwardLogTable;
use Application\Model\InviteInviterAwardLogTable;

use Application\Model\PlayClickLogTable;
use Application\Model\PlayCouponsLinkerTable;
use Application\Model\PlayMessageLogTable;
use Application\Model\PlayOrderInfoGameTable;
use Application\Model\PlayPostTable;
use Application\Model\PlaySearchFormValueTable;
use Application\Model\PlaySettingsTable;
use Application\Model\PlayUserWeiXinTable;
use Application\Model\PrizeLogTable;
use Application\Model\PrizeTable;
use Application\Model\PrizeUserDataTable;
use Application\Model\SocialCircleUsersTable;
use Application\Model\WeiXinReplyContentTable;
use Application\Model\WeiXinReplyKeywordTable;
use Application\Model\PlayAdminTable;
use Application\Model\PlayAdminCashTable;
use Application\Model\PlayAttachTable;
use Application\Model\PlayAuthCodeTable;
use Application\Model\PlayCouponsTable;
use Application\Model\PlayMarketTable;
use Application\Model\PlayMarketSettingTable;
use Application\Model\PlayOrderInfoTable;
use Application\Model\PlayOrderActionTable;
use Application\Model\PlayRegionTable;
use Application\Model\PlayShopTable;
use Application\Model\PlayUserTable;
use Application\Model\PlayCouponCodeTable;
use Application\Model\PlayFeedbackTable;
use Application\Model\PlayUserLinkerTable;
use Application\Model\PlayShareTable;
use Application\Model\PlayActivityTable;
use Application\Model\PlayActivityTagTable;
use Application\Model\PlayActivityCouponTable;
use Application\Model\PlayNewsTable;
use Application\Model\PlayRegionLinkerTable;
use Application\Model\PlayIndexBlockTable;
use Application\Model\PlayFocusMapTable;
use Application\Model\PlayMaBaoBaoTable;
use Application\Model\PlayTalkStoryTable;
use Application\Model\PlayTalkPriseTable;
use Application\Model\PlayLabelTable;
use Application\Model\PlayLabelMainTable;
use Application\Model\PlayLabelLinkerTable;
use Application\Model\PlayOrganizerTable;
use Application\Model\PlayOrganizerGameTable;
use Application\Model\PlayGameInfoTable;
use Application\Model\PlayGameTimeTable;
use Application\Model\PlayGamePriceTable;
use Application\Model\PlayGameTagTable;
use Application\Model\PlayUserCollectTable;
use Application\Model\PlayUserMessageTable;
use Application\Model\PlayUserBabyTable;
use Application\Model\PlayMessagePushTable;
use Application\Model\PlayPushTable;
use Application\Model\PlayLinkOrganizerShopTable;
use Application\Model\PlayOrganizerTouchTable;
use Application\Model\ActivitySnoopyOrderTable;
use Application\Model\ActivitySnoopyVerifyCodeTable;
use Application\Model\ActivityBabygogogoBatchTable;
use Application\Model\ActivityBabygogogoUserinfoTable;
use Application\Model\ActivityYouyouVerifyCodeTable;
use Application\Model\PlayGameAttributeLinkTable;
use Application\Model\PlayGameAttributeTable;
use Zend\ServiceManager\ServiceManager;
use Application\Model\PlayWebActivityTable;
use Application\Model\WeixinDituiLogTable;
use Application\Model\WeixinMenuTable;
use Application\Model\PlayGroupBuyTable;
use Application\Model\PlayAdminWorkLogTable;
use Application\Model\PlayTagsTable;
use Application\Model\PlayTagsLinkTable;
use Application\Model\PlayNearbyTable;
use Application\Model\AuthMenuTable;
use Application\Model\AuthAccessTable;
use Application\Model\AuthGroupTable;
use Application\Model\CashCouponCityTable;
use Application\Model\CashCouponGoodTable;
use Application\Model\CashCouponTable;
use Application\Model\CashCouponUserTable;
use Application\Model\PlayCashShareTable;
use Application\Model\PlayIntegralTable;
use Application\Model\IntegralUserTable;
use Application\Model\StationTable;
use Application\Model\PlayCityTable;
use Application\Model\PlayShopStrategyTable;
use Application\Model\PlayWelfareIntegralTable;
use Application\Model\PlayWelfareRebateTable;
use Application\Model\PlayWelfareCashTable;
use Application\Model\PlayInviteContentTable;
use Application\Model\PlayGoodCommentTable;
use Application\Model\PlayTaskIntegralTable;
use Application\Model\QualifyTable;
use Application\Model\PlayCodeUsedTable;
use Application\Model\PlayBusinessGroupTable;
use Application\Model\PlayWelfareTable;
use Application\Model\PlayOrganizerAccountTable;
use Application\Model\PlayOrganizerAccountLogTable;
use Application\Model\PlayContractsLinkUsedTable;
use Application\Model\PlayContractLinkGoodTable;
use Application\Model\PlayContractLinkPriceTable;
use Application\Model\PlayAwardLogTable;
use Application\Model\PlayAwardTable;
use Application\Model\PlayOrderOtherDataTable;
use Application\Model\PlayPrivatePartyTable;
use Application\Model\PlayGroundTable;
use Application\Model\PlayInventoryTable;
use Application\Model\PlayOrganizeraccountAuditTable;
use Application\Model\PlayOrganizerCodeLogTable;
use Application\Model\PlayPreMoneyLogTable;
use Application\Model\PlayPreLogTable;
use Application\Model\PlayInventoryLogTable;
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
        $c_info = $this->getClientinfo($_SERVER['HTTP_USER_AGENT'], isset($_SERVER['HTTP_USERAGENT'])?$_SERVER['HTTP_USERAGENT']:'');

        $encode_p=false;
        if(isset($_POST['p'])){
            $encryption = new Mcrypt();
            $decrypt_p = $encryption->decrypt($_POST['p']);
            $encode_p=json_decode($decrypt_p);
        }
//        session_start();
        try {
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
                'run_time'=>round(microtime(true)-RUN_T1,3)
            ));
        } catch (\Exception $e) {
            //暂不做处理
        }

    }
    //获取客户端信息  ios是自定义的useragent
    public function getClientinfo($http_user_agent, $ios_user_agent)
    {
        //ios android weixin other...
        $res = array('client' => 0, 'ver' => 0,'api_ver'=>0);
        if ($ios_user_agent) {
            $http_user_agent = $ios_user_agent;
        }
        if(strpos($http_user_agent, 'wft')!==false){
            //ios or android
            preg_match('/wft\/(android|ios)\/client\/(.*)$/', $http_user_agent, $matches);    //正则匹配  _all所有
            $res = array('client' => $matches[1], 'ver' => $matches[2],'api_ver'=>(int)$_SERVER['HTTP_VER']);
        }elseif(strpos($http_user_agent, 'MicroMessenger')!==false){
            //微信
            preg_match('/MicroMessenger\/(.*?)\s/', $http_user_agent, $matches);    //正则匹配  _all所有
            $res = array('client' => 'weixin', 'ver' => $matches[2],'api_ver'=>(int)$_SERVER['HTTP_VER']);
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
        return [
            'factories' => [
                //写入日志
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

                'Application\Module\PlayGroundTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_ground', $Adapter);
                    return new PlayGroundTable($table);
                },

                'Application/Module/PlayAdminCashTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_admin_cash', $Adapter);
                    return new PlayAdminCashTable($table);
                },
                'Application/Module/PlayOrderInsureTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_order_insure', $Adapter);
                    return new PlayOrderInsureTable($table);
                },
                'Application/Module/PlayInviteContentTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_invite_content', $Adapter);
                    return new PlayInviteContentTable($table);
                },

                'Application/Module/PlayGoodCommentTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_good_comment', $Adapter);
                    return new PlayGoodCommentTable($table);
                },
                'Application/Module/PlayPostTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_post', $Adapter);
                    return new PlayPostTable($table);
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

                'Application/Module/PlaySearchFormValueTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_search_form_value', $Adapter);
                    return new PlaySearchFormValueTable($table);
                },

                'Application/Module/PlaySettingsTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_settings', $Adapter);
                    return new PlaySettingsTable($table);
                },

                'Application/Module/PrizeTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_prize', $Adapter);
                    return new PrizeTable($table);
                },

                'Application/Module/PrizeUserDataTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_prize_userdata', $Adapter);
                    return new PrizeUserDataTable($table);
                },

                'Application/Module/PrizeLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_prize_log', $Adapter);
                    return new PrizeLogTable($table);
                },

                'Application/Module/PlayMessageLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_message_log', $Adapter);
                    return new PlayMessageLogTable($table);
                },
                'Application/Module/PlayCouponsLinkerTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_coupons_linker', $Adapter);
                    return new PlayCouponsLinkerTable($table);
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

                'Application/Module/PlayUserWeiXinTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_user_weixin', $Adapter);
                    return new PlayUserWeiXinTable($table);
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

                'Application/Module/PlayActivityCouponTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_activity_coupon', $Adapter);
                    return new PlayActivityCouponTable($table);
                },

                'Application/Module/PlayNewsTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_news', $Adapter);
                    return new PlayNewsTable($table);
                },

                'Application/Module/PlayRegionLinkerTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_region_linker', $Adapter);
                    return new PlayRegionLinkerTable($table);
                },

                'Application/Module/PlayIndexBlockTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_index_block', $Adapter);
                    return new PlayIndexBlockTable($table);
                },

                'Application/Module/PlayFocusMapTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_focus_map', $Adapter);
                    return new PlayFocusMapTable($table);
                },

                'Application/Module/PlayMaBaoBaoTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_mabaobao', $Adapter);
                    return new PlayMaBaoBaoTable($table);
                },

                'Application/Module/PlayTalkStoryTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('activity_talk_story', $Adapter);
                    return new PlayTalkstoryTable($table);
                },

                'Application/Module/PlayTalkPriseTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('activity_talk_prise', $Adapter);
                    return new PlayTalkPriseTable($table);
                },

                'Application/Module/PlayLabelTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_label', $Adapter);
                    return new PlayLabelTable($table);
                },

                'Application/Module/PlayLabelMainTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_label_main', $Adapter);
                    return new PlayLabelMainTable($table);
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

                'Application/Module/PlayGamePriceTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_game_price', $Adapter);
                    return new PlayGamePriceTable($table);
                },

                'Application/Module/PlayGameTagTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_game_tag', $Adapter);
                    return new PlayGameTagTable($table);
                },

                'Application/Module/PlayOrderInfoGameTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_order_info_game', $Adapter);
                    return new PlayOrderInfoGameTable($table);
                },

                'Application/Module/WeiXinReplyContentTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('weixin_reply_content', $Adapter);
                    return new WeiXinReplyContentTable($table);
                },

                'Application/Module/WeiXinReplyKeywordTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('weixin_reply_keyword', $Adapter);
                    return new WeiXinReplyKeywordTable($table);
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

                'Application/Module/PlayMessagePushTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_message_push', $Adapter);
                    return new PlayMessagePushTable($table);
                },

                'Application/Module/PlayPushTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_push', $Adapter);
                    return new PlayPushTable($table);
                },

                'Application/Module/PlayLinkOrganizerShopTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_linker_organizer_shop', $Adapter);
                    return new PlayLinkOrganizerShopTable($table);
                },

                'Application/Module/PlayOrganizerTouchTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_organizer_touch', $Adapter);
                    return new PlayOrganizerTouchTable($table);
                },

                'Application/Module/ActivitySnoopyOrderTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('activity_snoopy_order', $Adapter);
                    return new ActivitySnoopyOrderTable($table);
                },

                'Application/Module/ActivitySnoopyVerifyCodeTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('activity_snoopy_verify_code', $Adapter);
                    return new ActivitySnoopyVerifyCodeTable($table);
                },

                'Application/Module/PlayGameAttributeLinkTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_game_attribute_link', $Adapter);
                    return new PlayGameAttributeLinkTable($table);
                },

                'Application/Module/PlayClickLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_click_log', $Adapter);
                    return new PlayClickLogTable($table);
                },

                'Application/Module/PlayGameAttributeTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_game_attribute', $Adapter);
                    return new PlayGameAttributeTable($table);
                },

                'Application/Module/SocialCircleUsersTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('social_circle_users', $Adapter);
                    return new SocialCircleUsersTable($table);
                },

                'Application/Module/PlayWebActivityTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_web_activity', $Adapter);
                    return new PlayWebActivityTable($table);
                },

                'Application/Module/WeixinDituiLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('weixin_ditui_log', $Adapter);
                    return new WeixinDituiLogTable($table);
                },

                'Application/Module/WeixinMenuTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('weixin_menu', $Adapter);
                    return new WeixinMenuTable($table);
                },

                'Application/Module/ActivityBabygogogoBatchTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('activity_babygogogo_batch', $Adapter);
                    return new ActivityBabygogogoBatchTable($table);
                },

                'Application/Module/ActivityBabygogogoUserinfoTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('activity_babygogogo_userinfo', $Adapter);
                    return new ActivityBabygogogoUserinfoTable($table);
                },

                'Application/Module/ActivityYouyouVerifyCodeTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('activity_youyou_verify_code', $Adapter);
                    return new ActivityYouyouVerifyCodeTable($table);
                },
                'Application/Module/PlayGroupBuyTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_group_buy', $Adapter);
                    return new PlayGroupBuyTable($table);
                },

                'Application/Module/PlayAdminWorkLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_admin_work_log', $Adapter);
                    return new PlayAdminWorkLogTable($table);
                },

                'Application/Module/PlayTagsTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_tags', $Adapter);
                    return new PlayTagsTable($table);
                },

                'Application/Module/PlayTagsLinkTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_tags_link', $Adapter);
                    return new PlayTagsLinkTable($table);
                },

                'Application/Module/PlayNearbyTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_nearby', $Adapter);
                    return new PlayNearbyTable($table);
                },
                'Application/Module/AuthMenuTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_auth_menu_rule', $Adapter);
                    return new AuthMenuTable($table);
                },
                'Application/Module/AuthAccessTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_auth_group_access', $Adapter);
                    return new AuthAccessTable($table);
                },
                'Application/Module/AuthGroupTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_auth_group', $Adapter);
                    return new AuthGroupTable($table);
                },
                'Application/Module/QualifyTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_qualify_coupon', $Adapter);
                    return new QualifyTable($table);
                },

                'Application/Module/CashCouponCityTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_cashcoupon_city', $Adapter);
                    return new CashCouponCityTable($table);
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
                'Application/Module/CashCouponTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_cash_coupon', $Adapter);
                    return new CashCouponTable($table);
                },
                'Application/Module/PlayCashShareTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_cash_share', $Adapter);
                    return new PlayCashShareTable($table);
                },
                'Application/Module/PlayIntegralTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_integral', $Adapter);
                    return new PlayIntegralTable($table);
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

                'Application/Module/PlayCityTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_city', $Adapter);
                    return new PlayCityTable($table);
                },

                'Application/Module/PlayShopStrategyTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_shop_strategy', $Adapter);
                    return new PlayShopStrategyTable($table);
                },

                'Application/Module/PlayWelfareIntegralTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_welfare_integral', $Adapter);
                    return new PlayWelfareIntegralTable($table);
                },

                'Application/Module/PlayWelfareRebateTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_welfare_rebate', $Adapter);
                    return new PlayWelfareRebateTable($table);
                },

                'Application/Module/PlayWelfareCashTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_welfare_cash', $Adapter);
                    return new PlayWelfareCashTable($table);
                },

                'Application/Module/PlayTaskIntegralTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_task_integral', $Adapter);
                    return new PlayTaskIntegralTable($table);
                },
                //add kylin
                'Application/Module/AppGameCouponAddscorelogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('app_game_coupon_addscorelog', $Adapter);
                    return new AppGameCouponAddscorelogTable($table);
                },
                //add qinyuan
                'Application/Module/PlayContractsLinkTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_contracts_link', $Adapter);
                    return new PlayContractsLinkTable($table);
                },

                //add qinyuan
                'Application/Module/PlayContractsTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_contracts', $Adapter);
                    return new PlayContractsTable($table);
                },

                //add qinyuan
                'Application/Module/PlayContractsPriceTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_contracts_price', $Adapter);
                    return new PlayContractsPriceTable($table);
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

                'Application/Module/InviteRuleLog' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('invite_rule_log', $Adapter);
                    return new InviteRuleLogTable($table);
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


                'Application/Module/PlayCodeUsedTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_code_used', $Adapter);
                    return new PlayCodeUsedTable($table);
                },

                'Application/Module/PlayBusinessGroupTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_business_group', $Adapter);
                    return new PlayBusinessGroupTable($table);
                },

                'Application/Module/PlayWelfareTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_welfare', $Adapter);
                    return new PlayWelfareTable($table);
                },

                //add qinyuan
                'Application/Module/PlayContractActionTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_contract_action', $Adapter);
                    return new PlayContractActionTable($table);
                },

                'Application/Module/PlayOrganizerAccountTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_organizer_account', $Adapter);
                    return new PlayOrganizerAccountTable($table);
                },

                'Application/Module/PlayOrganizerAccountLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_organizer_account_log', $Adapter);
                    return new PlayOrganizerAccountLogTable($table);
                },

                'Application/Module/PlayContractsLinkUsedTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_contracts_link_used', $Adapter);
                    return new PlayContractsLinkUsedTable($table);
                },

                'Application/Module/PlayContractLinkGoodTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_contract_link_good', $Adapter);
                    return new PlayContractLinkGoodTable($table);
                },

                'Application/Module/PlayContractLinkPriceTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_contract_link_price', $Adapter);
                    return new PlayContractLinkPriceTable($table);
                },
                'Application/Module/AwardLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_award_log', $Adapter);
                    return new PlayAwardLogTable($table);
                },
                'Application/Module/AwardTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_award', $Adapter);
                    return new PlayAwardTable($table);
                },

                'Application/Module/PlaySearchLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_search_log', $Adapter);
                    return new PlayContractLinkPriceTable($table);
                },

                'Application/Module/PlayOrderOtherDataTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_order_otherdata', $Adapter);
                    return new PlayOrderOtherDataTable($table);
                },

                'Application/Module/PlayPrivatePartyTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_private_party', $Adapter);
                    return new PlayPrivatePartyTable($table);
                },

                'Application/Module/PlayUserAssociatesTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_user_associates', $Adapter);
                    return new PlayUserAssociatesTable($table);
                },

                //v3.3
                'Application/Module/PlayExcerciseBaseTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_excercise_base', $Adapter);
                    return new PlayExcerciseBaseTable($table);
                },
                'Application/Module/PlayExcerciseMeetingTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_excercise_meeting', $Adapter);
                    return new PlayExcerciseMeetingTable($table);
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

                'Application/Module/PlayInventoryTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_inventory', $Adapter);
                    return new PlayInventoryTable($table);
                },

                'Application/Module/PlayOrganizeraccountAuditTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_organizeraccount_audit', $Adapter);
                    return new PlayOrganizeraccountAuditTable($table);
                },

                'Application/Module/PlayPreMoneyLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_pre_money_log', $Adapter);
                    return new PlayPreMoneyLogTable($table);
                },

                'Application/Module/PlayPreLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_pre_log', $Adapter);
                    return new PlayPreLogTable($table);
                },

                'Application/Module/PlayInventoryLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_inventory_log', $Adapter);
                    return new PlayInventoryLogTable($table);
                },

                'Application/Module/PlayOrganizerCodeLogTable' => function ($sm) {
                    $db = $sm->get('config')['db'];
                    $Adapter = new Adapter($db);
                    $table = new TableGateway('play_organizer_code_log', $Adapter);
                    return new PlayOrganizerCodeLogTable($table);
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

            ]
        ];
    }

    public function getAutoloaderConfig()
    {
        return [
            'Zend\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__,
                    'Deyi' => 'vendor/Deyi'
                ]
            ]
        ];
    }
}
