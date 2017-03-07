<?php
namespace Deyi;

//add kylin
use Application\Model\AppGameCouponAddscorelogTable;
use Application\Model\CashCouponTable;
use Application\Model\IntegralUserTable;
use Application\Model\PlayAccountTable;
use Application\Model\PlayClientUpdateTable;
use Application\Model\PlayExcerciseBaseTable;
use Application\Model\PlayExcerciseCodeTable;
use Application\Model\PlayExcerciseEventTable;
use Application\Model\PlayExcercisePriceTable;
use Application\Model\PlayExcerciseScheduleTable;
use Application\Model\PlayExcerciseMeetingTable;
use Application\Model\PlayExcerciseShopTable;
use Application\Model\PlayMarketSettingTable;
use Application\Model\PlayOrderActionTable;
use Application\Model\PlayOrderInsureTable;
use Application\Model\PlayOrderOtherDataTable;
use Application\Model\PlayPatchUpdateTable;
use Application\Model\PlayPrivatePartyTable;
use Application\Model\PlayRegionTable;
use Application\Model\PlaySignInTable;
use Application\Model\PlayUserLinkerTable;
use Deyi\GetCacheData\CityCache;
use library\Service\System\Cache\RedCache;
use WebActivity\Model\GameScore;
use WebActivity\Model\GameScoreLog;
use WebActivity\Model\ShareToken;
use WebActivity\Model\GameAddScoreLog;
use Application\Model\InviteTokenTable;
use Application\Model\InviteRuleTable;
use Application\Model\InviteRuleLogTable;
use Application\Model\InviteMemberTable;
use Application\Model\InviteRecieverAwardLogTable;
use Application\Model\InviteInviterAwardLogTable;
//end
use Application\Model\PlayActivityCouponTable;
use Application\Model\PlayAdminTable;
use Application\Model\PlayAdminCashTable;
use Application\Model\PlayCashShareTable;
use Application\Model\PlayClickLogTable;
use Application\Model\PlayCouponCodeTable;
use Application\Model\PlayCouponsLinkerTable;
use Application\Model\PlayCouponsTable;
use Application\Model\PlayGroupBuyTable;
use Application\Model\PlayIndexBlockTable;
use Application\Model\PlayIndexNewTable;
use Application\Model\PlayLabelLinkerTable;
use Application\Model\PlayLabelTable;
use Application\Model\PlayLikeTable;
use Application\Model\PlayMessageLogTable;
use Application\Model\PlayOrderInfoGameTable;
use Application\Model\PlayOrderInfoTable;
use Application\Model\PlayPostTable;
use Application\Model\PlaySearchFormValueTable;
use Application\Model\PlaySearchLogTable;
use Application\Model\PlaySettingsTable;
use Application\Model\PlayShareTable;
use Application\Model\PlayShopTable;
use Application\Model\PlayUserTable;
use Application\Model\PlayUserWeiXinTable;
use Application\Model\PrizeLogTable;
use Application\Model\PrizeTable;
use Application\Model\PrizeUserDataTable;
use Application\Model\PlayOrganizerTable;
use Application\Model\PlayOrganizerGameTable;
use Application\Model\PlayGameInfoTable;
use Application\Model\PlayGameTimeTable;
use Application\Model\PlayGamePriceTable;
use Application\Model\PlayGameTagTable;
use Application\Model\SocialChatMsgTable;
use Application\Model\SocialCircleMsgPostTable;
use Application\Model\SocialCircleMsgTable;
use Application\Model\SocialCircleTable;
use Application\Model\SocialCircleUsersTable;
use Application\Model\WeiXinReplyContentTable;
use Application\Model\WeiXinReplyKeywordTable;
use Application\Model\WeixinDituiLogTable;
use Application\Model\WeixinMenuTable;
use Application\Model\PlayUserCollectTable;
use Application\Model\PlayUserMessageTable;
use Application\Model\PlayUserBabyTable;
use Application\Model\PlayGameAttributeLinkTable;
use Application\Model\PlayGameAttributeTable;
use Application\Model\PlayActivityTable;
use Application\Model\PlayAdminWorkLogTable;
use Zend\Db\Adapter\Adapter;
use Zend\Stdlib\ArrayObject;

use Application\Model\PlayMessagePushTable;
use Application\Model\PlayPushTable;
use Application\Model\PlayLinkOrganizerShopTable;
use Application\Model\PlayOrganizerTouchTable;
use Application\Model\ActivitySnoopyOrderTable;
use Application\Model\ActivitySnoopyVerifyCodeTable;
use Application\Model\ActivityBabygogogoBatchTable;
use Application\Model\ActivityBabygogogoUserinfoTable;

use Application\Model\PlayWebActivityTable;
use Application\Model\PlayTagsTable;
use Application\Model\PlayTagsLinkTable;
use Application\Model\PlayNearbyTable;
use Application\Model\PlayCityTable;
use Application\Model\AuthAccessTable;
use Application\Model\PlayShopStrategyTable;
use Application\Model\PlayWelfareIntegralTable;
use Application\Model\PlayWelfareRebateTable;
use Application\Model\PlayWelfareCashTable;
use Application\Model\PlayIntegralTable;
use Application\Model\PlayInviteContentTable;
use Application\Model\PlayGoodCommentTable;
use Application\Model\PlayLabelMainTable;
use Application\Model\PlayTaskIntegralTable;
use Application\Model\QualifyTable;
use Application\Model\PlayContractsTable;
use Application\Model\PlayContractsLinkTable;
use Application\Model\AuthMenuTable;
use Application\Model\CashCouponCityTable;
use Application\Model\CashCouponUserTable;
use Application\Model\CashCouponGoodTable;
use Application\Model\PlayCodeUsedTable;
use Application\Model\PlayBusinessGroupTable;
use Application\Model\PlayAccountLogTable;
use Application\Model\PlayWelfareTable;
use Application\Model\PlayOrganizerAccountTable;
use Application\Model\PlayOrganizerAccountLogTable;
use Application\Model\PlayContractActionTable;
use Application\Model\PlayContractsLinkUsedTable;
use Application\Model\PlayContractLinkGoodTable;
use Application\Model\PlayContractLinkPriceTable;

use Application\Model\PlayGroundTable;
use Application\Model\PlayInventoryTable;
use Application\Model\PlayOrganizeraccountAuditTable;
use Application\Model\PlayPreMoneyLogTable;
use Application\Model\PlayPreLogTable;
use Application\Model\PlayInventoryLogTable;
use Application\Model\PlayOrganizerCodeLogTable;
use Application\Model\PlayDistributionDetailTable;
use Application\Model\PlayDistributionLogTable;

trait BaseController
{
    //add kylin
    private $appGameCouponAddscorelogObj;
    private $appGameScoreObj;
    private $appGameScoreLogObj;
    private $appShareTokenObj;
    private $appGameAddScoreLogObj;
    private $InviteTokenObj;
    private $InviteRuleObj;
    private $InviteRuleLogObj;
    private $InviteMemberObj;
    private $InviteRecieverAwardLogObj;
    private $InviteInviterAwardLogObj;


    //end
    private $adminObj;
    private $admincashObj;
    private $cashshareObj;
    private $attachObj;
    private $couponsObj;
    private $marketObj;
    private $marketSettingObj;
    private $orderInfoObj;
    private $orderActionObj;
    private $regionObj;
    private $shopObj;
    private $userObj;
    private $PlayCouponCodeTable;
    private $PlayFeedbackTable;
    private $PlayShareTable;
    private $PlayUserLinkerTable;
    private $PlayAuthCodeTable;
    private $PlayCouponsLinkerTable;
    private $userLinkerObj;
    private $userAssociatesObj;
    private $orderInsureObj;
    private $activityObj;
    private $activityTagObj;
    private $PlayLikeTable;
    private $activityCouponObj;
    private $newsObj;
    private $PlayPostTable;
    private $PlaySettingsTable;
    private $regionLinkerObj;
    private $indexBlockObj;
    private $indexNewObj;
    private $PrizeTable;
    private $PrizeUserDataTable;
    private $PrizeLogTable;
    private $focusMapObj;
    private $PlaySearchLogTable;
    private $PlaySearchFormValueTable;
    private $maBaoBaoObj;
    private $talkStoryObj;
    private $talkPriseObj;
    private $PlayClickLogTable;
    private $labelObj;
    private $labelmainObj;
    private $labelLinkerObj;
    private $PlayMessageLogTable;
    private $organizerObj;
    private $organizerGameObj;
    private $gameInfoObj;
    private $gameTimeObj;
    private $gamePriceObj;
    private $orderInfoGameObj;
    private $gameTagObj;
    private $WeiXinReplyContent;
    private $WeiXinReplyKeyword;
    private $userCollectObj;
    private $userMessageObj;
    private $userBabyObj;
    private $UserWeiXinTable;
    private $messagePushObj;
    private $pushObj;
    private $linkOrganizerShopObj;
    private $organizerTouchObj;
    private $activitySnoopyOrderObj;
    private $activitySnoopyVerifyCodeObj;
    private $activityBabygogogoBatchObj;
    private $activityBabygogogoUserinfoObj;
    private $activityYouyouVerifyCodeObj;
    private $weixinDituiLogObj;
    private $weixinMenuObj;
    private $gameAttributeObj;
    private $gameAttributeLinkObj;
    private $socialCircleUsersTableObj;
    private $socialCircleTableObj;
    private $socialCircleMsgTableObj;
    private $socialCircleMsgPostTableObj;
    private $socialChatMsgTableObj;
    private $socialFriendsTableObj;
    private $webActivityObj;
    private $GroupBuy;
    private $adminWorkLogObj;
    private $AccountTable;
    private $AccountLogTable;
    private $tagsObj;
    private $tagsLinkObj;
    private $nearbyObj;
    private $authAccessObj;
    private $authGroupObj;
    private $authMenuObj;
    private $qualifyObj;
    private $integralUserObj;
    private $cashCouponObj;
    private $cashCouponUserObj;
    private $cashCouponGoodObj;
    private $cashCouponCityObj;
    private $stationObj;
    private $cityObj;
    private $strategyObj;
    private $welfareIntegralObj;
    private $welfareRebateObj;
    private $welfareCashObj;
    private $integralObj;
    private $goodCommentObj;
    private $inviteContentObj;
    private $taskIntegralObj;
    private $PlaySignInTable;
    private $contractsPriceObj;
    private $contractsLinkObj;
    private $contractsObj;
    private $PlayOrderOtherDataTable;
    private $codeUsedObj;
    private $businessGroupObj;

    private $welfareObj;
    private $organizerAccountObj;
    private $organizerAccountLogObj;
    private $contractsLinkUsedObj;
    private $contractActionObj;
    private $contractLinkGoodObj;
    private $contractLinkPriceObj;
    private $PlayPrivatePartyTable;
    private $privatePartyObj;
    private $groundObj;
    private $inventoryObj;
    private $organizeraccountAuditObj;
    private $preMoneyLogObj;
    private $preLogObj;
    private $inventoryLogObj;
    private $organizerCodeLogObj;
    private $distributionDetailObj;
    private $distributionLogObj;

    private $config;
    private $AdapterObj;
    public $P;
    private $MongoDB;

    //v3.3
    private $PlayExcerciseBaseTable;
    private $PlayExcerciseEventTable;
    private $PlayExcerciseCodeTable;
    private $PlayExcercisePriceTable;
    private $PlayExcerciseScheduleTable;
    private $PlayExcerciseShopTable;
    private $PlayExcerciseMeetingTable;

    /**
     * 单例模式  默认数据库wft
     * @return \MongoDB
     */
    public function _getMongoDB()
    {
        if ($this->MongoDB) {
            return $this->MongoDB;
        } else {
            $m = new \MongoClient('mongodb://127.0.0.1:27017');
            $this->MongoDB = $m->wft;

            return $this->MongoDB;
        }
    }

    /**
     * 获取内流加密的uid
     * 解密uid 获取用户登录uid
     * @param $original|是否返回原始数组
     * @return bool|int
     */
    public function getWapUid($original=false)
    {
        $params_p = isset($_GET['p']) && $_GET['p'] ? $_GET['p'] : '0';
        if (!$params_p) {
            return false;
        }
        $p = preg_replace(['/-/', '/_/'], ['+', '/'], $params_p);
        $encryption = new \Deyi\Mcrypt();
        $data = json_decode($encryption->decrypt($p), true);//数组  uid and timestamp
        if($original){
            return $data;
        }else{
            if ($data && isset($data['uid'])) {//property_exists($data, 'uid')
                $uid = $data['uid'];
            } else {
                $uid = 0;
            }
            return $uid;
        }

    }


    /**
     * @return Adapter;
     */
    public function _getAdapter()
    {
        if ($this->AdapterObj) {
            return $this->AdapterObj;
        } else {
            $this->AdapterObj = new Adapter($this->_getConfig('config')['db']);

            return $this->AdapterObj;
        }
    }

    /********************** mongoDb ********************/
    /**
     * @return \MongoCollection;
     */
    public function _getMdbSocialCircle()
    {
        return $this->_getMongoDB()->social_circle;
    }

    /**
     * @return \MongoCollection;
     */
    public function _getMdbSocialCircleMsg()
    {
        return $this->_getMongoDB()->social_circle_msg;
    }

    /**
     * 评分表
     * @return \MongoCollection;
     */
    public function _getMdbGradingRecords()
    {
        return $this->_getMongoDB()->grading_records;
    }

    /**
     * @return \MongoCollection;
     */
    public function _getMdbSocialCircleMsgPost()
    {
        return $this->_getMongoDB()->social_circle_msg_post;
    }


    /**
     * @return \MongoCollection;
     */
    public function _getMdbConsultPost()
    {
        return $this->_getMongoDB()->consult_post;
    }


    /**
     * @return \MongoCollection;
     */
    public function _getMdbSocialChatMsg()
    {
        return $this->_getMongoDB()->social_chat_msg;
    }

    /**
     * @return \MongoCollection;
     */
    public function _getMdbSocialFriends()
    {
        return $this->_getMongoDB()->social_friends;
    }

    /**
     * @return \MongoCollection;
     */
    public function _getMdbSocialCircleUsers()
    {
        return $this->_getMongoDB()->social_circle_users;
    }

    /**
     * @return \MongoCollection;
     */
    public function _getMdbSocialPrise()
    {
        return $this->_getMongoDB()->social_prise;
    }

    public function _getMdbNearBy()
    {
        return $this->_getMongoDB()->near_by;
    }

    /**
     * @return \MongoCollection;
     */

    public function _getMdbWeiPost()
    {
        return $this->_getMongoDB()->wei_post;
    }

    /**
     * @return \MongoCollection;
     */

    public function _getMdbJoinTogether()
    {
        return $this->_getMongoDB()->join_together;
    }

    /********************** mongoDb end ********************/


    /**
     * @return PlayAdminTable;
     */
    public function _getPlayAdminTable()
    {
        if (null === $this->adminObj) {
            $this->adminObj = $this->getServiceLocator()
                ->get('Application/Module/PlayAdminTable');
        }

        return $this->adminObj;
    }

    /**
     * @return PlayClientUpdateTable
     */
    public function _getPlayClientUpdateTable()
    {

        return   $this->adminObj = $this->getServiceLocator()
                ->get('Application/Module/PlayClientUpdateTable');
    }

    /**
     * @return PlayPatchUpdateTable
     */
    public function _getPlayPatchUpdateTable()
    {

        return   $this->adminObj = $this->getServiceLocator()
            ->get('Application/Module/PlayPatchUpdateTable');
    }


    /**
     * @return PlayAdminCashTable;
     */
    public function _getPlayAdminCashTable()
    {
        if (null === $this->admincashObj) {
            $this->admincashObj = $this->getServiceLocator()
                ->get('Application/Module/PlayAdminCashTable');
        }

        return $this->admincashObj;
    }
    /**
     * @return PlayCashShareTable;
     */
    public function _getPlayCashShareTable()
    {
        if (null === $this->cashshareObj) {
            $this->cashshareObj = $this->getServiceLocator()
                ->get('Application/Module/PlayCashShareTable');
        }

        return $this->cashshareObj;
    }

    /**
     * @return PlaySearchLogTable;
     */
    public function _getPlaySearchLogTable()
    {
        if (null === $this->PlaySearchLogTable) {
            $this->PlaySearchLogTable = $this->getServiceLocator()
                ->get('Application/Module/PlaySearchLogTable');
        }

        return $this->PlaySearchLogTable;
    }

    /**
     * @return PrizeTable
     */
    public function _getPrizeTableTable()
    {
        if (null === $this->PrizeTable) {
            $this->PrizeTable = $this->getServiceLocator()
                ->get('Application/Module/PrizeTable');
        }

        return $this->PrizeTable;
    }


    /**
     * @return PlaySearchFormValueTable;
     */
    public function _getPlaySearchFormValueTable()
    {
        if (null === $this->PlaySearchFormValueTable) {
            $this->PlaySearchFormValueTable = $this->getServiceLocator()
                ->get('Application/Module/PlaySearchFormValueTable');
        }

        return $this->PlaySearchFormValueTable;
    }

    /**
     * @return PrizeUserDataTable
     */
    public function _getPrizeUserDataTable()
    {
        if (null === $this->PrizeUserDataTable) {
            $this->PrizeUserDataTable = $this->getServiceLocator()
                ->get('Application/Module/PrizeUserDataTable');
        }

        return $this->PrizeUserDataTable;
    }

    /**
     * @return PrizeLogTable
     */
    public function _getPrizeLogTable()
    {
        if (null === $this->PrizeLogTable) {
            $this->PrizeLogTable = $this->getServiceLocator()
                ->get('Application/Module/PrizeLogTable');
        }

        return $this->PrizeLogTable;
    }

    public function _getPlayAttachTable()
    {
        if (null === $this->attachObj) {
            $this->attachObj = $this->getServiceLocator()
                ->get('Application/Module/PlayAttachTable');
        }

        return $this->attachObj;
    }

    /**
     * @return PlaySettingsTable;
     */
    public function _getPlaySettingsTable()
    {
        if (null === $this->PlaySettingsTable) {
            $this->PlaySettingsTable = $this->getServiceLocator()
                ->get('Application/Module/PlaySettingsTable');
        }

        return $this->PlaySettingsTable;
    }

    /**
     * @return PlayLikeTable;
     */
    public function _getPlayLikeTable()
    {
        if (null === $this->PlayLikeTable) {
            $this->PlayLikeTable = $this->getServiceLocator()
                ->get('Application/Module/PlayLikeTable');
        }

        return $this->PlayLikeTable;
    }

    /**
     * @return PlaySignInTable;
     */
    public function _getPlaySignInTable()
    {
        if (null === $this->PlaySignInTable) {
            $this->PlaySignInTable = $this->getServiceLocator()
                ->get('Application/Module/PlaySignInTable');
        }

        return $this->PlaySignInTable;
    }

    /**
     * @return PlayAuthCodeTable
     */
    public function _getPlayAuthCodeTable()
    {
        if (null === $this->PlayAuthCodeTable) {
            $this->PlayAuthCodeTable = $this->getServiceLocator()
                ->get('Application/Module/PlayAuthCodeTable');
        }

        return $this->PlayAuthCodeTable;
    }

    /**
     * @return PlayCouponCodeTable;
     */
    public function _getPlayCouponCodeTable()
    {
        if (null === $this->PlayCouponCodeTable) {
            $this->PlayCouponCodeTable = $this->getServiceLocator()
                ->get('Application/Module/PlayCouponCodeTable');
        }

        return $this->PlayCouponCodeTable;
    }

    /**
     * @return PlayCouponsTable
     */
    public function _getPlayCouponsTable()
    {
        if (null === $this->couponsObj) {
            $this->couponsObj = $this->getServiceLocator()
                ->get('Application/Module/PlayCouponsTable');
        }

        return $this->couponsObj;
    }

    public function _getPlayMarketTable()
    {
        if (null === $this->marketObj) {
            $this->marketObj = $this->getServiceLocator()
                ->get('Application/Module/PlayMarketTable');
        }

        return $this->marketObj;
    }

    public function _getPlayInviteContentTable()
    {
        if (null === $this->inviteContentObj) {
            $this->inviteContentObj = $this->getServiceLocator()
                ->get('Application/Module/PlayInviteContentTable');
        }

        return $this->inviteContentObj;
    }

    /**
     * @return PlayGoodCommentTable;
     */
    public function _getPlayGoodCommentTable()
    {
        if (null === $this->goodCommentObj) {
            $this->goodCommentObj = $this->getServiceLocator()
                ->get('Application/Module/PlayGoodCommentTable');
        }

        return $this->goodCommentObj;
    }

    public function _getPlayTaskIntegralTable()
    {
        if (null === $this->taskIntegralObj) {
            $this->taskIntegralObj = $this->getServiceLocator()
                ->get('Application/Module/PlayTaskIntegralTable');
        }

        return $this->taskIntegralObj;
    }

    /**
     * @return PlayMarketSettingTable;
     */
    public function _getPlayMarketSettingTable()
    {
        if (null === $this->marketSettingObj) {
            $this->marketSettingObj = $this->getServiceLocator()
                ->get('Application/Module/PlayMarketSettingTable');
        }

        return $this->marketSettingObj;
    }

    /**
     * @return PlayOrderInfoTable;
     */
    public function _getPlayOrderInfoTable()
    {
        if (null === $this->orderInfoObj) {
            $this->orderInfoObj = $this->getServiceLocator()
                ->get('Application/Module/PlayOrderInfoTable');
        }

        return $this->orderInfoObj;
    }

    /**
     * @return PlayOrderActionTable;
     */
    public function _getPlayOrderActionTable()
    {
        if (null === $this->orderActionObj) {
            $this->orderActionObj = $this->getServiceLocator()
                ->get('Application/Module/PlayOrderActionTable');
        }

        return $this->orderActionObj;
    }

    /**
     * @return PlayRegionTable;
     */
    public function _getPlayRegionTable()
    {
        if (null === $this->regionObj) {
            $this->regionObj = $this->getServiceLocator()
                ->get('Application/Module/PlayRegionTable');
        }

        return $this->regionObj;
    }

    /**
     * @return PlayShopTable;
     */
    public function _getPlayShopTable()
    {
        if (null === $this->shopObj) {
            $this->shopObj = $this->getServiceLocator()
                ->get('Application/Module/PlayShopTable');
        }

        return $this->shopObj;
    }

    /**
     * @return PlayUserTable;
     */
    public function _getPlayUserTable()
    {
        if (null === $this->userObj) {
            $this->userObj = $this->getServiceLocator()
                ->get('Application/Module/PlayUserTable');
        }

        return $this->userObj;
    }

    /**
     * @return PlayUserWeiXinTable;
     */
    public function _getPlayUserWeiXinTable()
    {
        if (null === $this->UserWeiXinTable) {
            $this->UserWeiXinTable = $this->getServiceLocator()
                ->get('Application/Module/PlayUserWeiXinTable');
        }

        return $this->UserWeiXinTable;
    }

    /**
     * @return  PlayUserLinkerTable;
     */
    public function _getPlayUserLinkerTable()
    {
        if (null === $this->userLinkerObj) {
            $this->userLinkerObj = $this->getServiceLocator()
                ->get('Application/Module/PlayUserLinkerTable');
        }

        return $this->userLinkerObj;
    }

    /**
     * @return  PlayUserAssociates;
     */
    public function _getPlayUserAssociatesTable()
    {
        if (null === $this->userAssociatesObj) {
            $this->userAssociatesObj = $this->getServiceLocator()
                ->get('Application/Module/PlayUserAssociatesTable');
        }

        return $this->userAssociatesObj;
    }

    /**
     * @return  PlayOrderInsureTable;
     */
    public function _getPlayOrderInsureTable()
    {
        if (null === $this->orderInsureObj) {
            $this->orderInsureObj = $this->getServiceLocator()
                ->get('Application/Module/PlayOrderInsureTable');
        }

        return $this->orderInsureObj;
    }

    /**
     * @return WeixinDituiLogTable
     */
    public function _getWeixinDituiLogTable()
    {
        if (null === $this->weixinDituiLogObj) {
            $this->weixinDituiLogObj = $this->getServiceLocator()
                ->get('Application/Module/WeixinDituiLogTable');
        }

        return $this->weixinDituiLogObj;
    }

    /**
     * @return WeixinMenuTable
     */
    public function _getWeixinMenuTable()
    {
        if (null === $this->weixinMenuObj) {
            $this->weixinMenuObj = $this->getServiceLocator()
                ->get('Application/Module/WeixinMenuTable');
        }

        return $this->weixinMenuObj;
    }

    /**
     * @return PlayCouponsLinkerTable;
     */
    public function _getPlayCouponsLinkerTable()
    {
        if (null === $this->PlayCouponsLinkerTable) {
            $this->PlayCouponsLinkerTable = $this->getServiceLocator()
                ->get('Application/Module/PlayCouponsLinkerTable');
        }

        return $this->PlayCouponsLinkerTable;
    }


    public function _getPlayFeedbackTable()
    {
        if (null === $this->PlayFeedbackTable) {
            $this->PlayFeedbackTable = $this->getServiceLocator()
                ->get('Application/Module/PlayFeedbackTable');
        }

        return $this->PlayFeedbackTable;
    }

    /**
     * @return PlayShareTable;
     */
    public function _getPlayShareTable()
    {
        if (null === $this->PlayShareTable) {
            $this->PlayShareTable = $this->getServiceLocator()
                ->get('Application/Module/PlayShareTable');
        }

        return $this->PlayShareTable;
    }

    /**
     * @return PlayActivityTable;
     */
    public function _getPlayActivityTable()
    {
        if (null === $this->activityObj) {
            $this->activityObj = $this->getServiceLocator()
                ->get('Application/Module/PlayActivityTable');
        }

        return $this->activityObj;
    }

    public function _getPlayActivityTagTable()
    {
        if (null === $this->activityTagObj) {
            $this->activityTagObj = $this->getServiceLocator()
                ->get('Application/Module/PlayActivityTagTable');
        }

        return $this->activityTagObj;
    }

    /**
     * @return PlayPostTable;
     */
    public function _getPlayPostTable()
    {
        if (null === $this->PlayPostTable) {
            $this->PlayPostTable = $this->getServiceLocator()
                ->get('Application/Module/PlayPostTable');
        }

        return $this->PlayPostTable;
    }


    /**
     * @return PlayActivityCouponTable
     */
    public function _getPlayActivityCouponTable()
    {
        if (null === $this->activityCouponObj) {
            $this->activityCouponObj = $this->getServiceLocator()
                ->get('Application/Module/PlayActivityCouponTable');
        }

        return $this->activityCouponObj;
    }

    public function _getPlayNewsTable()
    {
        if (null === $this->newsObj) {
            $this->newsObj = $this->getServiceLocator()
                ->get('Application/Module/PlayNewsTable');
        }

        return $this->newsObj;
    }


    /**
     * @return PlayClickLogTable;
     */
    public function _getPlayClickLogTable()
    {
        if (null === $this->PlayClickLogTable) {
            $this->PlayClickLogTable = $this->getServiceLocator()
                ->get('Application/Module/PlayClickLogTable');
        }

        return $this->PlayClickLogTable;
    }

    public function _getPlayRegionLinkerTable()
    {
        if (null === $this->regionLinkerObj) {
            $this->regionLinkerObj = $this->getServiceLocator()
                ->get('Application/Module/PlayRegionLinkerTable');
        }

        return $this->regionLinkerObj;
    }

    /**
     * @return PlayIndexBlockTable
     */
    public function _getPlayIndexBlockTable()
    {
        if (null === $this->indexBlockObj) {
            $this->indexBlockObj = $this->getServiceLocator()
                ->get('Application/Module/PlayIndexBlockTable');
        }

        return $this->indexBlockObj;
    }

    /**
     * @return PlayIndexNewTable
     */
    public function _getPlayIndexNewTable()
    {
        if (null === $this->indexNewObj) {
            $this->indexNewObj = $this->getServiceLocator()
                ->get('Application/Module/PlayIndexNewTable');
        }

        return $this->indexNewObj;
    }

    public function _getPlayFocusMapTable()
    {
        if (null === $this->focusMapObj) {
            $this->focusMapObj = $this->getServiceLocator()
                ->get('Application/Module/PlayFocusMapTable');
        }

        return $this->focusMapObj;
    }

    public function _getPlayMaBaoBaoTable()
    {
        if (null === $this->maBaoBaoObj) {
            $this->maBaoBaoObj = $this->getServiceLocator()
                ->get('Application/Module/PlayMaBaoBaoTable');
        }

        return $this->maBaoBaoObj;
    }

    /**
     * @return PlayTalkStoryTable
     */
    public function _getPlayTalkStoryTable()
    {
        if (null === $this->talkStoryObj) {
            $this->talkStoryObj = $this->getServiceLocator()
                ->get('Application/Module/PlayTalkStoryTable');
        }

        return $this->talkStoryObj;
    }

    /**
     * @return PlayTalkPriseTable
     */
    public function _getPlayTalkPriseTable()
    {
        if (null === $this->talkPriseObj) {
            $this->talkPriseObj = $this->getServiceLocator()
                ->get('Application/Module/PlayTalkPriseTable');
        }

        return $this->talkPriseObj;
    }

    /**
     * @return PlayLabelTable;
     */
    public function _getPlayLabelTable()
    {
        if (null === $this->labelObj) {
            $this->labelObj = $this->getServiceLocator()
                ->get('Application/Module/PlayLabelTable');
        }

        return $this->labelObj;
    }

    /**
     * @return PlayLabelTableMain;
     */
    public function _getPlayLabelMainTable()
    {
        if (null === $this->labelmainObj) {
            $this->labelmainObj = $this->getServiceLocator()
                ->get('Application/Module/PlayLabelMainTable');
        }

        return $this->labelmainObj;
    }

    /**
     * @return PlayLabelLinkerTable;
     */
    public function _getPlayLabelLinkerTable()
    {
        if (null === $this->labelLinkerObj) {
            $this->labelLinkerObj = $this->getServiceLocator()
                ->get('Application/Module/PlayLabelLinkerTable');
        }

        return $this->labelLinkerObj;
    }

    /**
     * @return PlayMessageLogTable;
     */
    public function _getPlayMessageLogTable()
    {
        if (null === $this->PlayMessageLogTable) {
            $this->PlayMessageLogTable = $this->getServiceLocator()
                ->get('Application/Module/PlayMessageLogTable');
        }

        return $this->PlayMessageLogTable;
    }


    /**
     * @return PlayOrganizerTable;
     */
    public function _getPlayOrganizerTable()
    {
        if (null === $this->organizerObj) {
            $this->organizerObj = $this->getServiceLocator()
                ->get('Application/Module/PlayOrganizerTable');
        }

        return $this->organizerObj;
    }

    /**
     * @return PlayOrganizerGameTable;
     */
    public function _getPlayOrganizerGameTable()
    {
        if (null === $this->organizerGameObj) {
            $this->organizerGameObj = $this->getServiceLocator()
                ->get('Application/Module/PlayOrganizerGameTable');
        }

        return $this->organizerGameObj;
    }

    /**
     * @return PlayOrderOtherDataTable;
     */
    public function _getPlayOrderOtherDataTable()
    {
        if (null === $this->PlayOrderOtherDataTable) {
            $this->PlayOrderOtherDataTable = $this->getServiceLocator()
                ->get('Application/Module/PlayOrderOtherDataTable');
        }

        return $this->PlayOrderOtherDataTable;
    }

    /**
     * @return PlayGameInfoTable;
     */
    public function _getPlayGameInfoTable()
    {
        if (null === $this->gameInfoObj) {
            $this->gameInfoObj = $this->getServiceLocator()
                ->get('Application/Module/PlayGameInfoTable');
        }

        return $this->gameInfoObj;
    }

    /**
     * @return PlayGameTimeTable;
     */
    public function _getPlayGameTimeTable()
    {
        if (null === $this->gameTimeObj) {
            $this->gameTimeObj = $this->getServiceLocator()
                ->get('Application/Module/PlayGameTimeTable');
        }

        return $this->gameTimeObj;
    }

    /**
     * @return PlayGamePriceTable;
     */
    public function _getPlayGamePriceTable()
    {
        if (null === $this->gamePriceObj) {
            $this->gamePriceObj = $this->getServiceLocator()
                ->get('Application/Module/PlayGamePriceTable');
        }

        return $this->gamePriceObj;
    }

    /**
     * @return PlayOrderInfoGameTable;
     */
    public function _getPlayOrderInfoGameTable()
    {
        if (null === $this->orderInfoGameObj) {
            $this->orderInfoGameObj = $this->getServiceLocator()
                ->get('Application/Module/PlayOrderInfoGameTable');
        }

        return $this->orderInfoGameObj;
    }

    /**
     * @return PlayGameTagTable;
     */
    public function _getPlayGameTagTable()
    {
        if (null === $this->gameTagObj) {
            $this->gameTagObj = $this->getServiceLocator()
                ->get('Application/Module/PlayGameTagTable');
        }

        return $this->gameTagObj;
    }

    /**
     * @return PlayUserCollectTable;
     */
    public function _getPlayUserCollectTable()
    {
        if (null === $this->userCollectObj) {
            $this->userCollectObj = $this->getServiceLocator()
                ->get('Application/Module/PlayUserCollectTable');
        }

        return $this->userCollectObj;
    }

    /**
     * @return PlayUserMessageTable;
     */
    public function _getPlayUserMessageTable()
    {
        if (null === $this->userMessageObj) {
            $this->userMessageObj = $this->getServiceLocator()
                ->get('Application/Module/PlayUserMessageTable');
        }

        return $this->userMessageObj;
    }

    /**
     * @return PlayUserBabyTable;
     */
    public function _getPlayUserBabyTable()
    {
        if (null === $this->userBabyObj) {
            $this->userBabyObj = $this->getServiceLocator()
                ->get('Application/Module/PlayUserBabyTable');
        }

        return $this->userBabyObj;
    }


    /**
     * @return WeiXinReplyKeywordTable;
     */
    public function _getWeiXinReplyKeyword()
    {
        if (null === $this->WeiXinReplyKeyword) {
            $this->WeiXinReplyKeyword = $this->getServiceLocator()
                ->get('Application/Module/WeiXinReplyKeywordTable');
        }

        return $this->WeiXinReplyKeyword;
    }

    /**
     * @return WeiXinReplyContentTable;
     */
    public function _getWeiXinReplyContent()
    {
        if (null === $this->WeiXinReplyContent) {
            $this->WeiXinReplyContent = $this->getServiceLocator()
                ->get('Application/Module/WeiXinReplyContentTable');
        }

        return $this->WeiXinReplyContent;
    }

    /**
     * @return PlayMessagePushTable;
     */
    public function _getPlayMessagePushTable()
    {
        if (null === $this->messagePushObj) {
            $this->messagePushObj = $this->getServiceLocator()
                ->get('Application/Module/PlayMessagePushTable');
        }

        return $this->messagePushObj;
    }

    /**
     * @return PlayPushTable;
     */
    public function _getPlayPushTable()
    {
        if (null === $this->pushObj) {
            $this->pushObj = $this->getServiceLocator()
                ->get('Application/Module/PlayPushTable');
        }

        return $this->pushObj;
    }

    /**
     * @return PlayLinkOrganizerShopTable;
     */
    public function _getPlayLinkOrganizerShopTable()
    {
        if (null === $this->linkOrganizerShopObj) {
            $this->linkOrganizerShopObj = $this->getServiceLocator()
                ->get('Application/Module/PlayLinkOrganizerShopTable');
        }

        return $this->linkOrganizerShopObj;
    }

    /**
     * @return PlayOrganizerTouchTable;
     */
    public function _getPlayOrganizerTouchTable()
    {
        if (null === $this->organizerTouchObj) {
            $this->organizerTouchObj = $this->getServiceLocator()
                ->get('Application/Module/PlayOrganizerTouchTable');
        }

        return $this->organizerTouchObj;
    }

    /**
     * @return ActivitySnoopyOrderTable;
     */
    public function _getActivitySnoopyOrderTable()
    {
        if (null === $this->activitySnoopyOrderObj) {
            $this->activitySnoopyOrderObj = $this->getServiceLocator()
                ->get('Application/Module/ActivitySnoopyOrderTable');
        }

        return $this->activitySnoopyOrderObj;
    }

    //add kylin
    /**
     * @return InviteTokenTable;
     */
    public function _getInviteToken()
    {
        if (null === $this->InviteTokenObj) {
            $this->InviteTokenObj = $this->getServiceLocator()
                ->get('Application/Module/InviteTokenTable');
        }

        return $this->InviteTokenObj;
    }

    /**
     * @return InviteRuleTable;
     */
    public function _getInviteRule()
    {
        if (null === $this->InviteRuleObj) {
            $this->InviteRuleObj = $this->getServiceLocator()
                ->get('Application/Module/InviteRule');
        }

        return $this->InviteRuleObj;
    }

    /**
     * @return InviteRuleLogTable;
     */
    public function _getInviteRuleLog()
    {
        if (null === $this->InviteRuleLogObj) {
            $this->InviteRuleLogObj = $this->getServiceLocator()
                ->get('Application/Module/InviteRuleLog');
        }

        return $this->InviteRuleLogObj;
    }

    /**
     * @return InviteMemberTable;
     */
    public function _getInviteMember()
    {
        if (null === $this->InviteMemberObj) {
            $this->InviteMemberObj = $this->getServiceLocator()
                ->get('Application/Module/InviteMember');
        }

        return $this->InviteMemberObj;
    }

    /**
     * @return InviteRecieverAwardLogTable;
     */
    public function _getInviteRecieverAwardLog()
    {
        if (null === $this->InviteRecieverAwardLogObj) {
            $this->InviteRecieverAwardLogObj = $this->getServiceLocator()
                ->get('Application/Module/InviteRecieverAwardLog');
        }

        return $this->InviteRecieverAwardLogObj;
    }

    /**
     * @return InviteInviterAwardLogTable;
     */
    public function _getInviteInviterAwardLog()
    {
        if (null === $this->InviteInviterAwardLogObj) {
            $this->InviteInviterAwardLogObj = $this->getServiceLocator()
                ->get('Application/Module/InviteInviterAwardLog');
        }

        return $this->InviteInviterAwardLogObj;
    }


    /**
     * @return AppGameCouponAddscorelogTable;
     */
    public function _getAppGameCouponAddscorelogTable()
    {
        if (null === $this->appGameCouponAddscorelogObj) {
            $this->appGameCouponAddscorelogObj = $this->getServiceLocator()
                ->get('Application/Module/AppGameCouponAddscorelogTable');
        }

        return $this->appGameCouponAddscorelogObj;
    }

    /**
     * @return GameScore;
     */
    public function _getGameScore()
    {
        if (null === $this->appGameScoreObj) {
            $db = $this->getServiceLocator()
                ->get('Model/GameScore');
            $this->appGameScoreObj = new GameScore($db['read'], $db['write']);
        }

        return $this->appGameScoreObj;
    }


    /**
     * @return GameScoreLog;
     */
    public function _getGameScoreLog()
    {
        if (null === $this->appGameScoreLogObj) {
            $db = $this->getServiceLocator()
                ->get('Model/GameScoreLog');
            $this->appGameScoreLogObj = new GameScoreLog($db['read'], $db['write']);
        }

        return $this->appGameScoreLogObj;
    }


    /**
     * @return ShareToken;
     */
    public function _getShareToken()
    {
        if (null === $this->appShareTokenObj) {
            $db = $this->getServiceLocator()
                ->get('Model/ShareToken');
            $this->appShareTokenObj = new ShareToken($db['read'], $db['write']);
        }

        return $this->appShareTokenObj;
    }


    /**
     * @return ShareToken;
     */
    public function _getGameAddScoreLog()
    {
        if (null === $this->appGameAddScoreLogObj) {
            $db = $this->getServiceLocator()
                ->get('Model/GameAddScoreLog');
            $this->appGameAddScoreLogObj = new GameAddScoreLog($db['read'], $db['write']);
        }

        return $this->appGameAddScoreLogObj;
    }

    //end kylin


    /**
     * @return ActivitySnoopyVerifyCodeTable;
     */
    public function _getActivitySnoopyVerifyCodeTable()
    {
        if (null === $this->activitySnoopyVerifyCodeObj) {
            $this->activitySnoopyVerifyCodeObj = $this->getServiceLocator()
                ->get('Application/Module/ActivitySnoopyVerifyCodeTable');
        }

        return $this->activitySnoopyVerifyCodeObj;
    }

    /**
     * @return ActivityBabygogogoBatchTable
     */
    public function _getActivityBabygogogoBatchTable()
    {
        if (null === $this->activityBabygogogoBatchObj) {
            $this->activityBabygogogoBatchObj = $this->getServiceLocator()
                ->get('Application/Module/ActivityBabygogogoBatchTable');
        }

        return $this->activityBabygogogoBatchObj;
    }

    /**
     * @return ActivityBabygogogoUserinfoTable;
     */
    public function _getActivityBabygogogoUserinfoTable()
    {
        if (null === $this->activityBabygogogoUserinfoObj) {
            $this->activityBabygogogoUserinfoObj = $this->getServiceLocator()
                ->get('Application/Module/ActivityBabygogogoUserinfoTable');
        }

        return $this->activityBabygogogoUserinfoObj;
    }

    /**
     * @return ActivityYouyouVerifyCodeTable;
     */
    public function _getActivityYouyouVerifyCodeTable()
    {
        if (null === $this->activityYouyouVerifyCodeObj) {
            $this->activityYouyouVerifyCodeObj = $this->getServiceLocator()
                ->get('Application/Module/ActivityYouyouVerifyCodeTable');
        }

        return $this->activityYouyouVerifyCodeObj;
    }


    /**
     * @return PlayGameAttributeLinkTable
     */
    public function _getPlayGameAttributeLinkTable()
    {
        if (null === $this->gameAttributeLinkObj) {
            $this->gameAttributeLinkObj = $this->getServiceLocator()
                ->get('Application/Module/PlayGameAttributeLinkTable');
        }

        return $this->gameAttributeLinkObj;
    }

    /**
     * @return PlayGameAttributeTable;
     */
    public function _getPlayGameAttributeTable()
    {
        if (null === $this->gameAttributeObj) {
            $this->gameAttributeObj = $this->getServiceLocator()
                ->get('Application/Module/PlayGameAttributeTable');
        }

        return $this->gameAttributeObj;
    }

    /**
     * @return SocialCircleUsersTable;
     */
    public function _getSocialCircleUsersTable()
    {
        if (null === $this->socialCircleUsersTableObj) {
            $this->socialCircleUsersTableObj = $this->getServiceLocator()
                ->get('Application/Module/SocialCircleUsersTable');
        }

        return $this->socialCircleUsersTableObj;
    }

    /**
     * @return SocialCircleTable;
     */
    public function _getSocialCircleTable()
    {
        if (null === $this->socialCircleTableObj) {
            $this->socialCircleTableObj = $this->getServiceLocator()
                ->get('Application/Module/SocialCircleTable');
        }

        return $this->socialCircleTableObj;
    }

    /**
     * @return SocialCircleMsgTable;
     */
    public function _getSocialCircleMsgTable()
    {
        if (null === $this->socialCircleMsgTableObj) {
            $this->socialCircleMsgTableObj = $this->getServiceLocator()
                ->get('Application/Module/SocialCircleMsgTable');
        }

        return $this->socialCircleMsgTableObj;
    }

    /**
     * @return SocialCircleMsgPostTable;
     */
    public function _getSocialCircleMsgPostTable()
    {
        if (null === $this->socialCircleMsgPostTableObj) {
            $this->socialCircleMsgPostTableObj = $this->getServiceLocator()
                ->get('Application/Module/SocialCircleMsgPostTable');
        }

        return $this->socialCircleMsgPostTableObj;
    }

    /**
     * @return SocialChatMsgTable;
     */
    public function _getSocialChatMsgTable()
    {
        if (null === $this->socialChatMsgTableObj) {
            $this->socialChatMsgTableObj = $this->getServiceLocator()
                ->get('Application/Module/SocialChatMsgTable');
        }

        return $this->socialChatMsgTableObj;
    }

    /**
     * @return SocialCircleTable;
     */
    public function _getSocialFriendsTable()
    {
        if (null === $this->socialFriendsTableObj) {
            $this->socialFriendsTableObj = $this->getServiceLocator()
                ->get('Application/Module/SocialFriend');
        }

        return $this->socialFriendsTableObj;
    }

    /**
     * @return PlayWebActivityTable;
     */
    public function _getPlayWebActivityTable()
    {
        if (null === $this->webActivityObj) {
            $this->webActivityObj = $this->getServiceLocator()
                ->get('Application/Module/PlayWebActivityTable');
        }

        return $this->webActivityObj;
    }

    /**
     * @return PlayGroupBuyTable;
     */
    public function _getPlayGroupBuyTable()
    {
        if (null === $this->GroupBuy) {
            $this->GroupBuy = $this->getServiceLocator()
                ->get('Application/Module/PlayGroupBuyTable');
        }

        return $this->GroupBuy;
    }

    /**
     * @return playAdminWorkLogTable;
     */
    public function _getPlayAdminWorkLogTable()
    {
        if (null === $this->adminWorkLogObj) {
            $this->adminWorkLogObj = $this->getServiceLocator()
                ->get('Application/Module/PlayAdminWorkLogTable');
        }

        return $this->adminWorkLogObj;
    }


    /**
     * @return PlayAccountTable;
     */
    public function _getPlayAccountTable()
    {
        if (null === $this->AccountTable) {
            $this->AccountTable = $this->getServiceLocator()
                ->get('Application/Module/PlayAccountTable');
        }

        return $this->AccountTable;
    }

    /**
     * @return PlayAccountLogTable;
     */
    public function _getPlayAccountLogTable()
    {
        if (null === $this->AccountLogTable) {
            $this->AccountLogTable = $this->getServiceLocator()
                ->get('Application/Module/PlayAccountLogTable');
        }

        return $this->AccountLogTable;
    }


    /**
     * @return playTagsTable;
     */
    public function _getPlayTagsTable()
    {
        if (null === $this->tagsObj) {
            $this->tagsObj = $this->getServiceLocator()
                ->get('Application/Module/PlayTagsTable');
        }

        return $this->tagsObj;
    }

    /**
     * @return playTagsLinkTable;
     */
    public function _getPlayTagsLinkTable()
    {
        if (null === $this->tagsLinkObj) {
            $this->tagsLinkObj = $this->getServiceLocator()
                ->get('Application/Module/PlayTagsLinkTable');
        }

        return $this->tagsLinkObj;
    }

    /**
     * @return playNearbyTable;
     */
    public function _getPlayNearbyTable()
    {
        if (null === $this->nearbyObj) {
            $this->nearbyObj = $this->getServiceLocator()
                ->get('Application/Module/PlayNearbyTable');
        }

        return $this->nearbyObj;
    }

    /**
     * @return mixed
     */
    public function _getAuthAccessTable()
    {
        if (null === $this->authAccessObj) {
            $this->authAccessObj = $this->getServiceLocator()
                ->get('Application/Module/AuthAccessTable');
        }

        return $this->authAccessObj;
    }

    /**
     * @return mixed
     */
    public function _getAuthGroupTable()
    {
        if (null === $this->authGroupObj) {
            $this->authGroupObj = $this->getServiceLocator()
                ->get('Application/Module/AuthGroupTable');
        }

        return $this->authGroupObj;
    }

    /**
     * @return AuthMenuTable
     */
    public function _getAuthMenuTable()
    {
        if (null === $this->authMenuObj) {
            $this->authMenuObj = $this->getServiceLocator()
                ->get('Application/Module/AuthMenuTable');
        }

        return $this->authMenuObj;
    }

    /**
     * @return CashCouponTable;
     */
    public function _getCashCouponTable()
    {
        if (null === $this->cashCouponObj) {
            $this->cashCouponObj = $this->getServiceLocator()
                ->get('Application/Module/CashCouponTable');
        }

        return $this->cashCouponObj;
    }

    /**
     * @return CashCouponGoodTable
     */
    public function _getCashCouponGoodTable()
    {
        if (null === $this->cashCouponGoodObj) {
            $this->cashCouponGoodObj = $this->getServiceLocator()
                ->get('Application/Module/CashCouponGoodTable');
        }

        return $this->cashCouponGoodObj;
    }

    /**
     * @return CashCouponUserTable
     */
    public function _getCashCouponUserTable()
    {
        if (null === $this->cashCouponUserObj) {
            $this->cashCouponUserObj = $this->getServiceLocator()
                ->get('Application/Module/CashCouponUserTable');
        }

        return $this->cashCouponUserObj;
    }


    /**
     * @return IntegralUserTable;
     */
    public function _getPlayIntegralUserTable()
    {
        if (null === $this->integralUserObj) {
            $this->integralUserObj = $this->getServiceLocator()
                ->get('Application/Module/IntegralUserTable');
        }

        return $this->integralUserObj;
    }

    /**
     * @return QualifyTable
     */
    public function _getQualifyTable()
    {
        if (null === $this->qualifyObj) {
            $this->qualifyObj = $this->getServiceLocator()
                ->get('Application/Module/QualifyTable');
        }

        return $this->qualifyObj;
    }

    /**
     * @return CashCouponCityTable
     */
    public function _getCashCouponCityTable()
    {
        if (null === $this->cashCouponCityObj) {
            $this->cashCouponCityObj = $this->getServiceLocator()
                ->get('Application/Module/CashCouponCityTable');
        }

        return $this->cashCouponCityObj;
    }

    public function _getStationTable()
    {
        if (null === $this->stationObj) {
            $this->stationObj = $this->getServiceLocator()
                ->get('Application/Module/StationTable');
        }

        return $this->stationObj;
    }

    public function _getPlayCityTable()
    {
        if (null === $this->cityObj) {
            $this->cityObj = $this->getServiceLocator()
                ->get('Application/Module/PlayCityTable');
        }

        return $this->cityObj;
    }

    /**
     * @return PlayShopStrategyTable;
     */
    public function _getPlayShopStrategyTable()
    {
        if (null === $this->strategyObj) {
            $this->strategyObj = $this->getServiceLocator()
                ->get('Application/Module/PlayShopStrategyTable');
        }

        return $this->strategyObj;
    }

    /**
     * @return PlayWelfareIntegralTable;
     */
    public function _getPlayWelfareIntegralTable()
    {
        if (null === $this->welfareIntegralObj) {
            $this->welfareIntegralObj = $this->getServiceLocator()
                ->get('Application/Module/PlayWelfareIntegralTable');
        }

        return $this->welfareIntegralObj;
    }

    /**
     * @return PlayWelfareRebateTable;
     */
    public function _getPlayWelfareRebateTable()
    {
        if (NULL === $this->welfareRebateObj) {
            $this->welfareRebateObj = $this->getServiceLocator()
                ->get('Application/Module/PlayWelfareRebateTable');
        }

        return $this->welfareRebateObj;
    }

    /**
     * @return PlayWelfareCashTable;
     */
    public function _getPlayWelfareCashTable()
    {
        if (NULL === $this->welfareCashObj) {
            $this->welfareCashObj = $this->getServiceLocator()
                ->get('Application/Module/PlayWelfareCashTable');
        }

        return $this->welfareCashObj;
    }

    /**
     * @return PlayIntegralTable;
     */
    public function _getPlayIntegralTable()
    {
        if (null === $this->integralObj) {
            $this->integralObj = $this->getServiceLocator()
                ->get('Application/Module/PlayIntegralTable');
        }

        return $this->integralObj;
    }

    /**
     * @return PlayContractsTable;
     */
    public function _getPlayContractsTable()
    {
        if (NULL === $this->contractsObj) {
            $this->contractsObj = $this->getServiceLocator()
                ->get('Application/Module/PlayContractsTable');
        }

        return $this->contractsObj;
    }

    /**
     * @return PlayContractsLinkTable;
     */
    public function _getPlayContractsLinkTable()
    {
        if (NULL === $this->contractsLinkObj) {
            $this->contractsLinkObj = $this->getServiceLocator()
                ->get('Application/Module/PlayContractsLinkTable');
        }

        return $this->contractsLinkObj;
    }


    /**
     * @return PlayContractsPriceTable;
     */
    public function _getPlayContractsPriceTable()
    {
        if (NULL === $this->contractsPriceObj) {
            $this->contractsPriceObj = $this->getServiceLocator()
                ->get('Application/Module/PlayContractsPriceTable');
        }

        return $this->contractsPriceObj;
    }

    /**
     * @return PlayCodeUsedTable;
     */
    public function _getPlayCodeUsedTable()
    {
        if (NULL === $this->codeUsedObj) {
            $this->codeUsedObj = $this->getServiceLocator()
                ->get('Application/Module/PlayCodeUsedTable');
        }

        return $this->codeUsedObj;
    }

    /**
     * @return PlayBusinessGroupTable;
     */
    public function _getPlayBusinessGroupTable()
    {
        if (NULL === $this->businessGroupObj) {
            $this->businessGroupObj = $this->getServiceLocator()
                ->get('Application/Module/PlayBusinessGroupTable');
        }

        return $this->businessGroupObj;
    }


    /**
     * @return PlayWelfareTable;
     */
    public function _getPlayWelfareTable()
    {
        if (NULL === $this->welfareObj) {
            $this->welfareObj = $this->getServiceLocator()
                ->get('Application/Module/PlayWelfareTable');
        }
        return $this->welfareObj;
    }


    /**
     * @return PlayPrivatePartyTable;
     */
    public function _getPlayPrivatePartyTable()
    {
        if (NULL === $this->PlayPrivatePartyTable) {
            $this->PlayPrivatePartyTable = $this->getServiceLocator()
                ->get('Application/Module/PlayPrivatePartyTable');
        }
        return $this->PlayPrivatePartyTable;
    }



    /**
     * @return PlayContractActionTable;
     */
    public function _getPlayContractActionTable()
    {
        if (NULL === $this->contractActionObj) {
            $this->contractActionObj = $this->getServiceLocator()
                ->get('Application/Module/PlayContractActionTable');
        }
        return $this->contractActionObj;
    }

    /**
     * @return PlayOrganizerAccountTable;
     */
    public function _getPlayOrganizerAccountTable()
    {
        if (NULL === $this->organizerAccountObj) {
            $this->organizerAccountObj = $this->getServiceLocator()
                ->get('Application/Module/PlayOrganizerAccountTable');
        }
        return $this->organizerAccountObj;
    }

    /**
     * @return PlayOrganizerAccountLogTable;
     */
    public function _getPlayOrganizerAccountLogTable()
    {
        if (NULL === $this->organizerAccountLogObj) {
            $this->organizerAccountLogObj = $this->getServiceLocator()
                ->get('Application/Module/PlayOrganizerAccountLogTable');
        }
        return $this->organizerAccountLogObj;
    }

    /**
     * @return PlayContractsLinkUsedTable;
     */
    public function _getPlayContractsLinkUsedTable()
    {
        if (NULL === $this->contractsLinkUsedObj) {
            $this->contractsLinkUsedObj = $this->getServiceLocator()
                ->get('Application/Module/PlayContractsLinkUsedTable');
        }
        return $this->contractsLinkUsedObj;
    }

    /**
     * @return PlayContractLinkGoodTable;
     */
    public function _getPlayContractLinkGoodTable()
    {
        if (NULL === $this->contractLinkGoodObj) {
            $this->contractLinkGoodObj = $this->getServiceLocator()
                ->get('Application/Module/PlayContractLinkGoodTable');
        }
        return $this->contractLinkGoodObj;
    }

    /**
     * @return PlayContractLinkPriceTable;
     */
    public function _getPlayContractLinkPriceTable()
    {
        if (NULL === $this->contractLinkPriceObj) {
            $this->contractLinkPriceObj = $this->getServiceLocator()
                ->get('Application/Module/PlayContractLinkPriceTable');
        }
        return $this->contractLinkPriceObj;
    }

    //v3.3
    /**
     * @return PlayExcerciseBaseTable;
     */
    public function _getPlayExcerciseBaseTable()
    {
        if (NULL === $this->PlayExcerciseBaseTable) {
            $this->PlayExcerciseBaseTable = $this->getServiceLocator()
                ->get('Application/Module/PlayExcerciseBaseTable');
        }
        return $this->PlayExcerciseBaseTable;
    }
    /**
     * @return PlayExcerciseMeetingTable;
     */
    public function _getPlayExcerciseMeetingTable()
    {
        if (NULL === $this->PlayExcerciseMeetingTable) {
            $this->PlayExcerciseMeetingTable = $this->getServiceLocator()
                ->get('Application/Module/PlayExcerciseMeetingTable');
        }
        return $this->PlayExcerciseMeetingTable;
    }
    /**
     * @return PlayExcerciseEventTable;
     */
    public function _getPlayExcerciseEventTable()
    {
        if (NULL === $this->PlayExcerciseEventTable) {
            $this->PlayExcerciseEventTable = $this->getServiceLocator()
                ->get('Application/Module/PlayExcerciseEventTable');
        }
        return $this->PlayExcerciseEventTable;
    }
    /**
     * @return PlayExcerciseCodeTable;
     */
    public function _getPlayExcerciseCodeTable()
    {
        if (NULL === $this->PlayExcerciseCodeTable) {
            $this->PlayExcerciseCodeTable = $this->getServiceLocator()
                ->get('Application/Module/PlayExcerciseCodeTable');
        }
        return $this->PlayExcerciseCodeTable;
    }

    /**
     * @return PlayExcercisePriceTable
     */
    public function _getPlayExcercisePriceTable()
    {
        if (NULL === $this->PlayExcercisePriceTable) {
            $this->PlayExcercisePriceTable = $this->getServiceLocator()
                ->get('Application/Module/PlayExcercisePriceTable');
        }
        return $this->PlayExcercisePriceTable;
    }


    /**
     * @return PlayExcerciseScheduleTable
     */
    public function _getPlayExcerciseScheduleTable()
    {
        if (NULL === $this->PlayExcerciseScheduleTable) {
            $this->PlayExcerciseScheduleTable = $this->getServiceLocator()
                ->get('Application/Module/PlayExcerciseScheduleTable');
        }
        return $this->PlayExcerciseScheduleTable;
    }

    /**
     * @return PlayExcerciseShopTable
     */
    public function _getPlayExcerciseShopTable()
    {
        if (NULL === $this->PlayExcerciseShopTable) {
            $this->PlayExcerciseShopTable = $this->getServiceLocator()
                ->get('Application/Module/PlayExcerciseShopTable');
        }
        return $this->PlayExcerciseShopTable;
    }

    /**
     * @return AwardLogTable
     */
    public function _getAwardLogTable()
    {
        if (null === $this->awardLogTableObj) {
            $this->awardLogTableObj = $this->getServiceLocator()
                ->get('Application/Module/AwardLogTable');
        }

        return $this->awardLogTableObj;
    }
    /**
     * @return AwardTable
     */
    public function _getAwardTable()
    {
        if (null === $this->awardTableObj) {
            $this->awardTableObj = $this->getServiceLocator()
                ->get('Application/Module/AwardTable');
        }

        return $this->awardTableObj;
    }

    /**
     * @return PlayGroundTable
     */
    public function _getPlayGroundTable(){
        if (null === $this->groundObj) {
            $this->groundObj = $this->getServiceLocator()
                ->get('Application/Module/PlayGroundTable');
        }
        return $this->groundObj;
    }

    /**
     * @return PlayInventoryTable
     */
    public function _getPlayInventoryTable(){
        if (null === $this->inventoryObj) {
            $this->inventoryObj = $this->getServiceLocator()
                ->get('Application/Module/PlayInventoryTable');
        }
        return $this->inventoryObj;
    }

    /**
     * @return PlayOrganizeraccountAuditTable
     */
    public function _getPlayOrganizeraccountAuditTable(){
        if (null === $this->organizeraccountAuditObj) {
            $this->organizeraccountAuditObj = $this->getServiceLocator()
                ->get('Application/Module/PlayOrganizeraccountAuditTable');
        }
        return $this->organizeraccountAuditObj;
    }

    /**
     * @return PlayPreMoneyLogTable
     */
    public function _getPlayPreMoneyLogTable(){
        if (null === $this->preMoneyLogObj) {
            $this->preMoneyLogObj = $this->getServiceLocator()
                ->get('Application/Module/PlayPreMoneyLogTable');
        }
        return $this->preMoneyLogObj;
    }

    /**
     * @return PlayPreLogTable
     */
    public function _getPlayPreLogTable(){
        if (null === $this->preLogObj) {
            $this->preLogObj = $this->getServiceLocator()
                ->get('Application/Module/PlayPreLogTable');
        }
        return $this->preLogObj;
    }

    /**
     * @return PlayInventoryLogTable
     */
    public function _getPlayInventoryLogTable(){
        if (null === $this->inventoryLogObj) {
            $this->inventoryLogObj = $this->getServiceLocator()
                ->get('Application/Module/PlayInventoryLogTable');
        }
        return $this->inventoryLogObj;
    }

    /**
     * @return PlayOrganizerCodeLogTable
     */
    public function _getPlayOrganizerCodeLogTable(){
        if (null === $this->organizerCodeLogObj) {
            $this->organizerCodeLogObj = $this->getServiceLocator()
                ->get('Application/Module/PlayOrganizerCodeLogTable');
        }
        return $this->organizerCodeLogObj;
    }

    /**
     * @return PlayDistributionDetailTable
     */
    public function _getPlayDistributionDetailTable(){
        if (null === $this->distributionDetailObj) {
            $this->distributionDetailObj = $this->getServiceLocator()
                ->get('Application/Module/PlayDistributionDetailTable');
        }
        return $this->distributionDetailObj;
    }

    /**
     * @return PlayDistributionLogTable
     */
    public function _getPlayDistributionLogTable(){
        if (null === $this->distributionLogObj) {
            $this->distributionLogObj = $this->getServiceLocator()
                ->get('Application/Module/PlayDistributionLogTable');
        }
        return $this->distributionLogObj;
    }


    public function _getConfig()
    {
        if (null === $this->config) {
            $this->config = $this->getServiceLocator()
                ->get('config');
        }

        return $this->config;
    }

    //过滤单引号和双引号
    public function getPost($param = null, $default = null)
    {
        $p = $this->params()->fromPost($param, $default);
        if (is_numeric($p)) {
            return $p;
        }

        if (is_array($p)) {
            return $p;
        }

        return $p === null ? false : htmlspecialchars($p, ENT_QUOTES);
    }

    //过滤单引号和双引号
    public function getQuery($param = null, $default = null)
    {
        $p = $this->params()->fromQuery($param, $default);
        if (is_numeric($p)) {
            return $p;
        }
        if (is_array($p)) {
            return $p;
        }

        return $p === null ? false : htmlspecialchars($p, ENT_QUOTES);

    }


    /**
     * @param $info
     * @param null $backurl "javascript:location.href = document.referrer"  返回并刷新
     * @return \Zend\View\Model\ViewModel
     */
    public function _Goto($info, $backurl = 'javascript:location.href = document.referrer')
    {
        $v = new \Zend\View\Model\ViewModel([
            'info' => $info,
            'backurl' => $backurl
        ]);
        $v->setTemplate('/admin/index/warning');

        return $v;
    }

    //删除微信wap登录信息pass()方法使用到
    public function cleanWapCookie()
    {
        $untime = time() - 3600;  //失效时间
        setcookie('uid', 0, $untime, '/');
        setcookie('token', 0, $untime, '/');
        setcookie('open_id', 0, $untime, '/');
        setcookie('phone', 0, $untime, '/');
    }

    /**
     * 接口验证
     * @param bool $check_token 是否验证token
     * @return bool
     */
    public function pass($check_token = true)
    {
        $pass = false;
        // 如果是调试模式则不加密
        if (isset($_GET['debug']) and $_GET['debug'] == 1 and !isset($_POST['p'])) {
            $this->P = (object)$_POST;
            $pass = true;
        } else {
            $p = $this->params()->fromPost('p', false);
            if (!$p or is_numeric($p)) {
                $token = $_COOKIE['token'];
                $this->P = (object)$_POST;
                $uid = $_COOKIE['uid'];

                //判断是否需要验证token
                if($check_token or $uid>0){
                    $this->P->uid = $uid;
                    if ($this->checkToken($uid, $token)) {
                        $pass = true;
                    } else {
                        //清空cookie
                        $this->cleanWapCookie();
                        JsonResponse::$init_verification_token=false;
                        return false;
                    }
                }else{
                    //不需要验证的页面
                    return true;
                }
            } else {
                $encryption = new Mcrypt();
                $data = $encryption->decrypt($p);
                if (!$data) {
                    $pass = false;
                } else {
                    $this->P = json_decode($data);
                    if (!$this->P) {
                        $pass = false;
                    } else {
                        // 临时注释
                        if (isset($this->P->uid) and $this->P->uid and $check_token) {
                            if (!isset($this->P->token) or !$this->checkToken($this->P->uid, $this->P->token)) {
                                JsonResponse::$init_verification_token=false;
                                return false;
                            }
                        }
                        $pass = true;
                    }
                }
            }

        }

        if ($pass) {
            return true;
        } else {
            return false;
        }
    }


    //检查用户对应的token
    public function checkToken($uid, $token)
    {
        if (!$token or !$uid) {
            return false;
        }
        $red_token = RedCache::get('user_token_' . $uid);

        if ($red_token) {
            if ($token === $red_token) {
                return true;
            }
        }

        $user_data = $this->_getPlayUserTable()->get(['uid' => $uid]);
        RedCache::set('user_token_' . $uid, $user_data->token, 604800);
        if ($user_data->token === $token) {
            return true;
        } else {
            return false;
        }
    }

    //检查微信用户是否存在,状态是否正常
    public function checkWeiXinUser()
    {
        if (!isset($_COOKIE['open_id']) or !isset($_COOKIE['uid']) or !isset($_COOKIE['token'])) {
            return false;
        }

        if ($this->checkToken($_COOKIE['uid'], $_COOKIE['token'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * api接口获取参数  数字 字符串 json字符串
     * @param null $param
     * @param null $default
     * @return bool|string
     */
    public function getParams($param, $default = null, $encode = true)
    {
        $p = isset($this->P->$param) ? $this->P->$param : $default;
        if (is_numeric($p)) {
            return $p;
        }
        if ($encode) {
            return $p === null ? null : htmlspecialchars($p, ENT_QUOTES);
        } else {
            return $p;
        }
    }

    //解密分享的id
    public function getShareId()
    {
        $p = $this->params('id');
        $p = preg_replace(['/-/', '/_/'], ['+', '/'], $p);
        $encryption = new Mcrypt();

        return (int)$encryption->decrypt($p);

    }

    //获取城市,微信页面 api 通用
    public function getCity()
    {
        $_SERVER['HTTP_CITY']= $_SERVER['HTTP_CITY']?:$_COOKIE['city']?:$_COOKIE['sel_city'];
        $city = isset($_SERVER['HTTP_CITY']) ? urldecode($_SERVER['HTTP_CITY']) : '武汉';
        $city = in_array($city, CityCache::getCities()) ? $city : '武汉';
        return array_flip(CityCache::getCities())[$city];
    }


    //获取微信配置
    private function getwxConfig()
    {
        return $this->_getConfig()['wanfantian_weixin'];
    }

    /**
     * 根据相同的键合并二维数组 ,按arr2排序
     * 使用注意 保证count(arr2)>count(arr1)
     * @param $arr1
     * @param $arr2
     * @param $key |string  需要查找的键
     * @return Array
     */
    public function mergeArray($arr1, $arr2, $key)
    {
        $tmp = array();
        foreach ($arr1 as $k => $value) {
            $tmp[$value[$key]] = $k;
        }

        foreach ($arr2 as $k => $value) {
            if (isset($value[$key]) && isset($tmp[$value[$key]])) {
                $arr2[$k] = array_merge($arr2[$k], $arr1[$tmp[$value[$key]]]);
            }
        }

        return $arr2;
    }


    //将评论转为jsonArray
    public function msgToArray($message, $img_list = array())
    {
        if (stripos($message, '{') !== false) {
            //json
            $msg = json_decode($message, true);
        } else {
            $msg = array(
                array('t' => 1, 'val' => $message)
            );
        }

        foreach ($img_list as $v) {
            $msg[] = array('t' => 2, 'val' => $v);
        }

        return $msg;
    }

    //获取完整的图像地址
    public function getImgUrl($imgurl)
    {
        return $imgurl ? ((strpos($imgurl, '/') === 0) ? $this->_getConfig()['url'] . $imgurl : $imgurl) : '';
    }

    //返回电话,如果为空则返回400
    public function getPhone($phone=''){
        return $phone?$phone:'4008007221';
    }

    //当用户名为手机号时隐藏中间的号码
    public function hidePhoneNumber($phone)
    {

        if (is_numeric($phone) and strlen($phone) == 11) {
            return substr_replace($phone, '****', 3, 4);
        } else {
            return $phone;
        }
    }

    //根据天气获取游玩地的属性 // 后面 可由后台 自定义 处理
    public function getWeatherAttribute($d = 0)
    {

        if (!$this->getWeather()) {
            return array('室内场所');
        }

        $weatherData = json_decode($this->getWeather(), true);

        if (!$d) {
            $data = date('N');  //周几 1 到 7
        } else {
            if (($d + date('N')) <= 7) {
                $data = $d + date('N');  //周几 1 到 7
            } else {
                $data = $d + date('N') - 7;  //周几 1 到 7
            }
        }

        if (!$d) {
            $temperature = substr($weatherData['weather_data'][0]['date'],
                strlen('实时：') + strpos($weatherData['weather_data'][0]['date'], '实时：'),
                (strlen($weatherData['weather_data'][0]['date']) - strpos($weatherData['weather_data'][0]['date'],
                        '℃')) * (-1));
        } else {
            $temperature = ceil(((int)explode('~',
                        $weatherData['weather_data'][$d]['temperature'])[0] + (int)explode('~',
                        $weatherData['weather_data'][$d]['temperature'])[1]) / 2);
        }

        $weather = $weatherData['weather_data'][$d]['weather'];

        if (in_array($weather, array('小雪', '中雪', '小雪转中雪', '中雪转大雪', '大雪转暴雪', '阵雪'))) {
            return array('玩雪场');
        }

        if (in_array($weather, array('晴', '多云', '阴', '晴转多云', '晴转阴', '阴转晴', '阴转多云', '多云转晴', '多云转阴')) && $temperature >= 25) {
            return array('游泳馆', '室内场所');
        }

        if ($temperature <= 5 && in_array($weather, array('晴', '阴转晴', '多云转晴'))) {
            return array('晒太阳');
        }

        if ($temperature >= 5 && $temperature <= 25 && in_array($weather, array('晴', '多云', '阴', '晴转多云', '晴转阴', '阴转晴', '阴转多云', '多云转晴', '多云转阴'))) {
            if (in_array($data, array(1, 2, 3, 4))) {// 周一到 周四
                return array('露天商圈场馆');
            } else {
                return array('郊游');
            }
        }

        return array('室内场所');
    }

    public function getWeather()
    {
        $city = $this->getCity();
        $weather = RedCache::get('weather_data_' . $city);
        if ($weather) {
            return $weather;
        } else {
            $ak = $this->_getConfig()['weather_ak'];
            $city_code = urlencode(CityCache::getCities()[$city]);
            $m = json_decode(Request::get('http://api.map.baidu.com/telematics/v3/weather?location=' . $city_code . '&output=json&ak=' . $ak),
                'true');
            if ($m['status'] == 'success') {
                $weather = $m['results'][0];
                RedCache::set('weather_data_' . $city, json_encode($weather, JSON_UNESCAPED_UNICODE), 10 * 60 * 60);
                return json_encode($weather, JSON_UNESCAPED_UNICODE);
            } else {
                // todo 没获取到天气数据
                return null;

            }
        }

    }

    /**
     * 获取小编当月可以使用的奖励额度
     * @param $city
     * @return int
     */
    public function getEditMoney($city){
        $iam = $_COOKIE['id'];
        $limit = $this->_getPlayAdminTable()->get(['id'=>$iam]);
        $edu = $limit->limit;

        //计算已经发出的奖励
        $start = strtotime(date('Y-m'));
        $out = $this->_getPlayAccountLogTable()->fetchAll(['editor_id'=>$_COOKIE['id'],'action_type_id'=>[4,8,9,11,19,20,21,99],'dateline > ?'=>$start]);
        $pre_out = $this->_getPlayWelfareRebateTable()->fetchAll(['status'=>1,'editor_id'=>$_COOKIE['id'],'from_type'=>3,'create_time > ?'=>$start]);
        $now_money = 0;
        if($out){
            foreach($out as $o){
                $now_money = bcadd($o->flow_money, $now_money, 2);
            }
        }

        if($pre_out){
            foreach($pre_out as $o){
                $now_money = bcadd($o->single_rebate, $now_money, 2);
            }
        }

        $return = $this->_getPlayCityTable()->get(['city'=>$this->getAdminCity()])->return;
        $total = $this->_getPlayAccountLogTable()->fetchAll(['city'=>$city,'action_type_id'=>[4,8,9,11,19,20,21,99],'dateline > ?'=>$start]);
        if($total){
            $now_money2 = 0;
            foreach($total as $o){
                $now_money2 = bcadd($o->flow_money, $now_money2, 2);
            }
        }
        $remain = (($edu - $now_money)>($return-$now_money2))?($return-$now_money2):($edu - $now_money);
        return ($remain>0)?$remain:0;
    }

    /**
     * 把返回的数据集转换成Tree
     * @access public
     * @param array $list 要转换的数据集
     * @param string $pid parent标记字段
     * @param string $level level标记字段
     * @return array
     */
    public function toTree($list = null, $pk = 'id', $pid = 'pid', $child = '_child')
    {
        if (null === $list) {
            // 默认直接取查询返回的结果集合
            $list =   &$this->dataList;
        }
        // 创建Tree
        $tree = array();
        if (is_array($list)) {
            // 创建基于主键的数组引用
            $refer = array();

            foreach ($list as $key => $data) {
                $_key = is_object($data) ? $data->$pk : $data[$pk];
                $refer[$_key] =& $list[$key];
            }
            foreach ($list as $key => $data) {
                // 判断是否存在parent
                $parentId = is_object($data) ? $data->$pid : $data[$pid];
                $is_exist_pid = false;
                foreach ($refer as $k => $v) {
                    if ($parentId == $k) {
                        $is_exist_pid = true;
                        break;
                    }
                }
                if ($is_exist_pid) {
                    if (isset($refer[$parentId])) {
                        $parent =& $refer[$parentId];
                        $parent[$child][] =& $list[$key];
                    }
                } else {
                    $tree[] =& $list[$key];
                }
            }
        }

        return $tree;
    }

    /**
     * 将格式数组转换为树
     *
     * @param array $list
     * @param integer $level 进行递归时传递用的参数
     */
    private $formatTree; //用于树型数组完成递归格式的全局变量

    private function _toFormatTree($list, $level = 0, $title = 'title')
    {
        foreach ($list as $key => $val) {
            $tmp_str = str_repeat("&nbsp;", $level * 2);
            $tmp_str .= "└";

            $val['level'] = $level;
            $val['title_show'] = $level == 0 ? $val[$title] . "&nbsp;" : $tmp_str . $val[$title] . "&nbsp;";
            // $val['title_show'] = $val['id'].'|'.$level.'级|'.$val['title_show'];
            if (!array_key_exists('_child', $val)) {
                array_push($this->formatTree, $val);
            } else {
                $tmp_ary = $val['_child'];
                unset($val['_child']);
                array_push($this->formatTree, $val);
                $this->_toFormatTree($tmp_ary, $level + 1, $title); //进行下一层递归
            }
        }

        return;
    }

    public function toFormatTree($list, $title = 'title', $pk = 'id', $pid = 'pid', $root = 0)
    {
        $list = $this->list_to_tree($list, $pk, $pid, '_child', $root);
        $this->formatTree = array();
        $this->_toFormatTree($list, 0, $title);

        return $this->formatTree;
    }

    /**
     * 把返回的数据集转换成Tree
     * @param array $list 要转换的数据集
     * @param string $pid parent标记字段
     * @param string $level level标记字段
     * @return array
     */
    public function list_to_tree($list, $pk = 'id', $pid = 'pid', $child = '_child', $root = 0)
    {
        // 创建Tree
        $tree = array();
        if (is_array($list)) {
            // 创建基于主键的数组引用
            $refer = array();
            foreach ($list as $key => $data) {
                $refer[$data[$pk]] =& $list[$key];
            }
            foreach ($list as $key => $data) {
                // 判断是否存在parent
                $parentId = $data[$pid];
                if ($root == $parentId) {
                    $tree[] =& $list[$key];
                } else {
                    if (isset($refer[$parentId])) {
                        $parent =& $refer[$parentId];
                        $parent[$child][] =& $list[$key];
                    }
                }
            }
        }

        return $tree;
    }

    /**
     * 计算两组经纬度坐标 之间的距离
     * params ：lat1 纬度1； lng1 经度1； lat2 纬度2； lng2 经度2； len_type （1:m or 2:km);
     * return m or km
     */
    public function GetDistance($lat1, $lng1, $lat2, $lng2, $len_type = 1, $decimal = 2)
    {

        if (!defined('EARTH_RADIUS')) {
            define('EARTH_RADIUS', 6378.137); //地球半径
        }
        if (!defined('PI')) {
            define('PI', 3.1415926);
        }

        if (!$lat2 or !$lng2) {
            return 0;
        }
        $radLat1 = $lat1 * PI / 180.0;
        $radLat2 = $lat2 * PI / 180.0;
        $a = $radLat1 - $radLat2;
        $b = ($lng1 * PI / 180.0) - ($lng2 * PI / 180.0);
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2)));
        $s = $s * EARTH_RADIUS;
        $s = round($s * 1000);
        if ($len_type > 1) {
            $s /= 1000;
        }

        return round($s, $decimal);
    }

    //获取设置
    /**
     * @return array
     */
    public function getMarketSetting()
    {
        $data = $this->_getPlayMarketSettingTable()->get(['city' => $this->getCity()]);
        return $data;
    }

    //所有城市
    /**
     * @param int $mian 0只有地方 1主站 2全部 3主站＋全部
     * @return array
     */
    public function getAllCities($mian=0)
    {
        $adapter = $this->_getAdapter();

        $sql = 'select * from play_city where (is_close = 0 or is_close = 2)';
        if($mian==0 || $mian == 2){
            $sql .= ' and city != 1';
        }

        $sql .= ' order by city asc';
        $city = $adapter->query($sql, array());
        $city_kv = [];
        foreach ($city as $c) {
            $city_kv[$c['city'].''] = $c['city_name'];
        }

        return $city_kv;
    }

    /**
     * 用新增和保存一个控制器的情况，总站编辑不修改原数据的所有者
     * @param $post_city
     * @return string|\Zend\View\Model\ViewModel
     */
    public function getCreater($post_city){
        $creater = $this->getAdminCity();
        if($creater == 1){
            $cities = $this->getAllCities(1);
            if(!array_key_exists($post_city,$cities)){
                return $this->_Goto('参数错误');
            }
            $creater = $post_city;
        }
        return $creater;
    }

    //获得登录管理员 city
    public function getAdminCity()
    {
        return array_key_exists('city', $_COOKIE) ? $_COOKIE['city'] : 'WH';
    }

    public function isMain(){
        return ($this->getAdminCity()==1);
    }

    //所有角色
    public function getAllGroup()
    {
        $adapter = $this->_getAdapter();
        $sql = 'select * from play_auth_group where status = 1 ;';
        $group = $adapter->query($sql, array());

        $groups = [];
        foreach ($group as $k => $g) {
            $groups[$g['id']] = $g['title'];
        }

        return $groups;
    }

    //字符串截取
    public function width_cut($str, $l = 8)
    {
        $short = '';
        $len = 0;
        for ($i = 0; $i < mb_strlen($str, 'UTF-8'); $i++) {
            $ch = mb_substr($str, $i, 1, 'UTF-8');
            $chlen = strlen($ch);
            if ($chlen == 3) {
                $chlen = 2;
            }
            $len += $chlen;
            if ($len <= $l) {
                $short .= $ch;
            }
        }
        return $short;
    }

    //积分余额明细宽度
    public function cut_withpoint($str, $l = 28)
    {
        if(!$str){
            return '';
        }
        $short = '';
        $len = 0;
        for ($i = 0; $i < mb_strlen($str, 'UTF-8'); $i++) {
            $ch = mb_substr($str, $i, 1, 'UTF-8');
            $chlen = strlen($ch);
            if ($chlen == 3) {
                $chlen = 2;
            }
            $len += $chlen;
            if ($len <= $l) {
                $short .= $ch;
            }
        }
        if( mb_strlen($str, 'UTF-8') > mb_strlen($short, 'UTF-8') ){
            $short .= '...';
        }
        return $short;
    }



    public function checkMid($mid)
    {
        if (strlen($mid) != 24) {  //默认长度 24
            return false;
        } else {
            return true;
        }
    }

    //判断浏览器是否微信内置
    public function is_weixin()
    {
        if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
            return true;
        }
        return false;
    }

    //判断浏览器是否玩翻天app内流
    public function is_wft()
    {
        if ( strpos($_SERVER['HTTP_USER_AGENT'], 'wft') !== false ) {
            return true;
        }
        return false;
    }

    public function checkPhone($backUrl)
    {
        if (!isset($_COOKIE['phone']) || !$_COOKIE['phone']) {
            //临时查询用户是否已绑定手机号
            $user_data = $this->_getPlayUserTable()->get(array('uid' => (int)$_COOKIE['uid']));
            if ($user_data->phone) {
                $untime = time() + 3600 * 24 * 17;  //失效时间
                setcookie('phone', $user_data->phone, $untime, '/');
                return true;
            } else {
                $url = $this->_getConfig()['url'] . "/web/wappay/register?uid={$_COOKIE['uid']}&tourl=" . urlencode($backUrl);
                header("Location: $url");
                exit;
            }

        }
    }

    //初始化用户 生成用户 生成验证信息
    public function userInit(WeiXinFun $weixin)
    {


        if (!$this->checkWeiXinUser()) {
            if (isset($_GET['code'])) {
                //todo 封装  存储相关信息，获取用户信息，生成cookie
                $accessTokenData = $weixin->getUserAccessToken($_GET['code']);

                if (isset($accessTokenData->access_token)) {
                    $token = md5(time() . $accessTokenData->access_token);
                    //先查询用户是否存在
                    $user_data = false;
                    if (!$accessTokenData->unionid) {
                        $accessTokenData->unionid = -1;
                    }
                    $user = $this->_getPlayUserWeiXinTable()->getUserInfo("play_user_weixin.open_id='{$accessTokenData->openid}' or play_user_weixin.unionid='{$accessTokenData->unionid}'");

                    if ($user) {
                        $user_data = $this->_getPlayUserTable()->get(array('uid' => $user->uid));
                    }
                    if ($user && $user_data) {
                        //初始化当前新微信号数据
                        $weixin = $this->_getPlayUserWeiXinTable()->get(array('open_id' => $accessTokenData->openid));
                        if (!$weixin) {
                            $this->_getPlayUserWeiXinTable()->insert(array(
                                'uid' => $user->uid,
                                'appid' => $this->getwxConfig()['appid'],
                                'open_id' => $accessTokenData->openid,
                                'unionid' => isset($accessTokenData->unionid) ? $accessTokenData->unionid : '',
                                'access_token_wap' => $accessTokenData->access_token,
                                'refresh_token_wap' => $accessTokenData->refresh_token,
                                'login_type' => 'weixin_wap', //微信授权表改为通用授权表
                            ));
                        }

                        $this->setCookie($user->uid, $user->token, $accessTokenData->openid, $user_data->phone);
                        return true;
                    } else {
                        if ($accessTokenData->scope == 'snsapi_userinfo') {
                            $userInfo = $weixin->getUserInfo($accessTokenData->access_token);
                            if (!$userInfo) {
                                //todo 错误处理机制
                                WriteLog::WriteLog('获取userInfo错误:' . print_r($userInfo, true));
                                return false;
                            }
                            $username = $userInfo->nickname;
                            $img = $userInfo->headimgurl;

                        } else {
                            $username = 'WeiXin' . time();
                            $img = '';
                        }

                        $this->_getPlayUserTable()->insert(array(
                            'username' => $username ? $username : '　',//用户名不能为空的BUG
                            'password' => '',
                            'token' => $token,
                            'mark_info' => 0,
                            'login_type' => 'weixin_wap',
                            'is_online' => 1,
                            'device_type' => '',
                            'dateline' => time(),
                            'status' => 1,
                            'img' => $img,
                            'city'=>$this->getCity()
                        ));
                        $uid = $this->_getPlayUserTable()->getlastInsertValue();
                        $status = $this->_getPlayUserWeiXinTable()->insert(array(
                            'uid' => $uid,
                            'appid' => $this->getwxConfig()['appid'],
                            'open_id' => $accessTokenData->openid,
                            'unionid' => isset($accessTokenData->unionid) ? $accessTokenData->unionid : '',
                            'access_token_wap' => $accessTokenData->access_token,
                            'refresh_token_wap' => $accessTokenData->refresh_token,
                            'login_type' => 'weixin_wap', //微信授权表改为通用授权表
                        ));

                        $this->setCookie($uid, $token, $accessTokenData->openid);

                        if (!$status) {
                            return false;
                        } else {
                            return true;
                        }
                    }
                } else {
                    //todo 错误处理机制
                    WriteLog::WriteLog('获取userAccessToken错误:' . print_r($accessTokenData, true));
                    return false;
                }
            } else {
                //todo 如果用户点了拒绝
                return false;
            }
        } else {
            return true;
        }
    }


    //微信token登录验证
    public function WeiXinArc($back_url){
        $weixin = new WeiXinFun($this->getwxConfig());
        if ($this->userInit($weixin) and $this->checkWeiXinUser()) {
            $this->checkPhone($back_url);
        } else {
            //todo 授权失败
            $toUrl = $weixin->getAuthorUrl($back_url, 'snsapi_userinfo');
            header("Location: $toUrl");
            exit;
        }
    }

    public function setCookie($uid, $token, $openid, $phone = '')
    {
        $untime = time() + 3600 * 24 * 17;  //失效时间
        setcookie('uid', $uid, $untime, '/');
        setcookie('token', $token, $untime, '/');
        setcookie('open_id', $openid, $untime, '/');
        setcookie('phone', $phone, $untime, '/');

        $_COOKIE['uid'] = $uid;
        $_COOKIE['token'] = $token;
        $_COOKIE['phone'] = $phone;
        $_COOKIE['open_id'] = $openid;
    }

    public function post_curl($url,$post,$city="武汉",$cookie,$ver=10){
        $header = array(
//            'Host:api.wanfantian.com',
//            'Content-Type: form-data; charset=utf-8',
          //  'Content-Length: ' . strlen($post),
            'VER:'.$ver,
            'CITY:'.urlencode($city),
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, 'open_id='.$cookie['open_id'].';token='.$cookie['token'].';uid='.$cookie['uid']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); //连接超时
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //下载超时


        $response  = curl_exec($ch);
//        var_dump($header);
        if ($response) {
            curl_close($ch);
            $this->checkAuthStatus($response);
            return $response;
        } else {
            curl_close($ch);
            return false;
        }
    }

    /**
     * 验权失败，清除cookie，并刷新本页
     */
    public function checkAuthStatus($response){
        $result = json_decode($response, true);
        $status = $result['status'];
        if($status == 1001){
            $this->cleanWapCookie();
            //刷新本页
            $url = $_SERVER['REQUEST_URI'];
            $config = $this->_getConfig();
            $host = $config['url'];

            $target = $host . $url;
            //$target = 'http://wan.wanfantian.com/web/wappay/nindex';
            header('Location: ' . $target);
        }
    }

    //wap定位
    public function getlocation(){
        $IPaddress='';
        if (isset($_SERVER)){
            if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
                $IPaddress = $_SERVER["HTTP_X_FORWARDED_FOR"];
            } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
                $IPaddress = $_SERVER["HTTP_CLIENT_IP"];
            } else {
                $IPaddress = $_SERVER["REMOTE_ADDR"];
            }
        } else {
            if (getenv("HTTP_X_FORWARDED_FOR")){
                $IPaddress = getenv("HTTP_X_FORWARDED_FOR");
            } else if (getenv("HTTP_CLIENT_IP")) {
                $IPaddress = getenv("HTTP_CLIENT_IP");
            } else {
                $IPaddress = getenv("REMOTE_ADDR");
            }
        }

//        var_dump($_SERVER['HTTP_X_REAL_IP']);
//        $IPaddress="221.226.5.1";
        $content = Request::get("http://api.map.baidu.com/location/ip?ak=9cBeAvrF76W2cM9t1q0Q32Ii&ip={$IPaddress}&coor=bd09ll");
        $json = json_decode($content);
        $lng = $json->{'content'}->{'point'}->{'x'};
        $lat = $json->{'content'}->{'point'}->{'y'};
        $map = new BaiduLocation();
        $location = $map->locationByGPS($lng,$lat);
        $addr =$location['address'];
        return array('addr'=>$addr,'city'=>$location['city']);
    }

    /**
     * 检查变量是否为空
     * @param object $_obj 需要检查的变量，可以是字符、数组等。
     * @return bool
     */
    public function IsN($_obj){
        $sType = gettype($_obj);

        if($sType == "string"){
            if($_obj === "0"){
                return false;
            }
        }

        if($sType == "integer"){
            if($_obj === 0){
                return false;
            }
        }

        return empty($_obj);
    }

    //保留标签
    public function strip_word_html($text, $allowed_tags = '<b><i><sup><sub><em><strong><u>')
    {
        mb_regex_encoding('UTF-8');

        $search = array('/&lsquo;/u', '/&rsquo;/u', '/&ldquo;/u', '/&rdquo;/u', '/&mdash;/u');
        $replace = array('\'', '\'', '"', '"', '-');

        $text = preg_replace($search, $replace, $text);

        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        if(mb_stripos($text, '/*') !== FALSE){
            $text = mb_eregi_replace('#/\*.*?\*/#s', '', $text, 'm');
        }

        $text = preg_replace(array('/<([0-9]+)/'), array('< $1'), $text);
        $text = strip_tags($text, $allowed_tags);

        $search = array('#<(strong|b)[^>]*>(.*?)</(strong|b)>#isu', '#<(em|i)[^>]*>(.*?)</(em|i)>#isu', '#<u[^>]*>(.*?)</u>#isu');
        $replace = array('<b>$2</b>', '<i>$2</i>', '<u>$1</u>');
        $text = preg_replace($search, $replace, $text);

        $num_matches = preg_match_all("/\<!--/u", $text, $matches);
        if($num_matches){
            $text = preg_replace('/\<!--(.)*--\>/isu', '', $text);
        }
        return $text;
    }

    public function json2html($json){
        $arr = json_decode($json);
        $str = '';

        foreach($arr as $a){
            if($a->t==2){
                $str .= '<img src="'.$a->val.'" /><br/>';
            }
            if($a->t==1){
                $str .= $a->val.'<br/>';
            }
        }

        return $str;
    }

    //用来过滤html
    public function trmhtml($text){
        $rm = '<img>';
        $text = str_replace('&nbsp;','',$text);
        //$text = $this->strip_word_html($text, $rm);
        $cit = str_replace('&nbsp;','',$text);

        $matchs = preg_match_all('/\<img\s.*?\/\>/u',$cit,$out);

        $highlights = [];
        if($matchs){
            $text = preg_replace('/\<img\s.*?\/\>/u', '$@$', $cit);

            $tag1 = explode('$@$',$text);
            $tag2 = $out[0];
            $highlights = [];
            $arr = count($tag1)>count($tag2)?$tag1:$tag2;
            foreach($arr as $k => $v){
                if($tag1[$k]){
                    $highlights[] = ['t'=>1,'val'=>$tag1[$k]];
                }
                if($out[0][$k]){
                    $img = preg_match('/<img.+src="?(.+.jpg|gif|bmp|png)"?.+>/isu',$out[0][$k],$match);
                    if($img){
                        $highlights[] = ['t'=>2,'val'=>$match[1]];
                    }
                }
            }
        }else{
            $highlights[] = ['t'=>1,'val'=>$cit];
        }
        $highlights = array_filter($highlights);

        return json_encode($highlights);
    }



    public function getShareInfoAction($share=array()){
        $weixin = new WeiXinFun($this->getwxConfig());
        $WxConfig = $weixin->getsignature();
        $WxConfig['url']=$this->_getConfig()['url'];

        return array($share,$WxConfig);

    }

    public function db_create_in($item_list, $field_name = '')
    {
        if (empty($item_list))
        {
            return $field_name . " IN ('') ";
        }
        else
        {
            if (!is_array($item_list))
            {
                $item_list = explode(',', $item_list);
            }
            $item_list = array_unique($item_list);
            $item_list_tmp = '';
            foreach ($item_list AS $item)
            {
                if ($item !== '')
                {
                    $item_list_tmp .= $item_list_tmp ? ",'$item'" : "'$item'";
                }
            }
            if (empty($item_list_tmp))
            {
                return $field_name . " IN ('') ";
            }
            else
            {
                return $field_name . ' IN (' . $item_list_tmp . ') ';
            }
        }
    }
    //去除所有空格换行等
    public function  replaceAllSpace($str){
        $search = array(" ","　","\n","\r","\t","\r\n");
        $replace = array("","","","","","");
        return str_replace($search, $replace, $str);
    }

    //记录日志 会邮件通知
    public function errorLog($msg)
    {
        if(is_object($msg) or is_array($msg)){
            $msg=print_r($msg,true);
        }
        $this->getServiceLocator()->get('Logger')->crit($msg);
    }


}
