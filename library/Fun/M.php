<?php
namespace library\Fun;


use library\Model\BaseTable;
use library\Model\PlayBannerSettingTable;
use library\Model\PlayBannerTable;
use library\Model\PlayMemberMoneyServiceTable;
use library\Model\PlayMemberTable;
use library\Model\PlayShareDataTable;
use library\Model\PlayUserAssociatesTable;
use library\Service\ServiceManager;

use library\Model\PlayAccountLogTable;
use library\Model\PlayAccountTable;
use library\Model\PlayClientUpdateTable;
use library\Model\PlayContractActionTable;
use library\Model\PlayContractsLinkTable;
use library\Model\PlayContractsPriceTable;
use library\Model\PlayContractsTable;
use library\Model\PlayExcerciseBaseTable;
use library\Model\PlayExcerciseMeetingTable;
use library\Model\PlayExcerciseCodeTable;
use library\Model\PlayExcerciseEventTable;
use library\Model\PlayExcercisePriceTable;
use library\Model\PlayExcerciseScheduleTable;
use library\Model\PlayExcerciseShopTable;
use library\Model\PlayOrderInsureTable;
use library\Model\PlayPatchUpdateTable;
use library\Model\AppGameCouponAddscorelogTable;
use library\Model\InviteTokenTable;
use library\Model\InviteRuleTable;
use library\Model\InviteRuleLogTable;
use library\Model\InviteMemberTable;
use library\Model\InviteRecieverAwardLogTable;
use library\Model\InviteInviterAwardLogTable;
use library\Model\PlayClickLogTable;
use library\Model\PlayCouponsLinkerTable;
use library\Model\PlayMessageLogTable;
use library\Model\PlayOrderInfoGameTable;
use library\Model\PlayPostTable;
use library\Model\PlaySearchFormValueTable;
use library\Model\PlaySettingsTable;
use library\Model\PlayUserWeiXinTable;
use library\Model\PrizeLogTable;
use library\Model\PrizeTable;
use library\Model\PrizeUserDataTable;
use library\Model\SocialCircleUsersTable;
use library\Model\WeiXinReplyContentTable;
use library\Model\WeiXinReplyKeywordTable;
use library\Model\PlayAdminTable;
use library\Model\PlayAdminCashTable;
use library\Model\PlayAuthCodeTable;
use library\Model\PlayCouponsTable;
use library\Model\PlayMarketSettingTable;
use library\Model\PlayOrderInfoTable;
use library\Model\PlayOrderActionTable;
use library\Model\PlayRegionTable;
use library\Model\PlayShopTable;
use library\Model\PlayUserTable;
use library\Model\PlayCouponCodeTable;
use library\Model\PlayUserLinkerTable;
use library\Model\PlayShareTable;
use library\Model\PlayActivityTable;
use library\Model\PlayActivityCouponTable;
use library\Model\PlayIndexBlockTable;
use library\Model\PlayTalkStoryTable;
use library\Model\PlayTalkPriseTable;
use library\Model\PlayLabelTable;
use library\Model\PlayLabelLinkerTable;
use library\Model\PlayOrganizerTable;
use library\Model\PlayOrganizerGameTable;
use library\Model\PlayGameInfoTable;
use library\Model\PlayGameTimeTable;
use library\Model\PlayGamePriceTable;
use library\Model\PlayGameTagTable;
use library\Model\PlayUserCollectTable;
use library\Model\PlayUserMessageTable;
use library\Model\PlayUserBabyTable;
use library\Model\PlayMessagePushTable;
use library\Model\PlayPushTable;
use library\Model\PlayLinkOrganizerShopTable;
use library\Model\PlayOrganizerTouchTable;
use library\Model\ActivitySnoopyOrderTable;
use library\Model\ActivitySnoopyVerifyCodeTable;
use library\Model\ActivityBabygogogoBatchTable;
use library\Model\ActivityBabygogogoUserinfoTable;
use library\Model\ActivityYouyouVerifyCodeTable;
use library\Model\PlayGameAttributeLinkTable;
use library\Model\PlayGameAttributeTable;
use library\Model\PlayWebActivityTable;
use library\Model\WeixinDituiLogTable;
use library\Model\WeixinMenuTable;
use library\Model\PlayGroupBuyTable;
use library\Model\PlayAdminWorkLogTable;
use library\Model\PlayTagsTable;
use library\Model\PlayTagsLinkTable;
use library\Model\PlayNearbyTable;
use library\Model\AuthMenuTable;
use library\Model\CashCouponCityTable;
use library\Model\CashCouponGoodTable;
use library\Model\CashCouponTable;
use library\Model\CashCouponUserTable;
use library\Model\PlayCashShareTable;
use library\Model\PlayIntegralTable;
use library\Model\IntegralUserTable;
use library\Model\PlayShopStrategyTable;
use library\Model\PlayWelfareIntegralTable;
use library\Model\PlayWelfareRebateTable;
use library\Model\PlayWelfareCashTable;
use library\Model\PlayGoodCommentTable;
use library\Model\QualifyTable;
use library\Model\PlayCodeUsedTable;
use library\Model\PlayBusinessGroupTable;
use library\Model\PlayWelfareTable;
use library\Model\PlayOrganizerAccountTable;
use library\Model\PlayOrganizerAccountLogTable;
use library\Model\PlayContractsLinkUsedTable;
use library\Model\PlayContractLinkGoodTable;
use library\Model\PlayContractLinkPriceTable;
use library\Model\PlayOrderOtherDataTable;
use library\Model\PlayPrivatePartyTable;
use library\Model\PlayGroundTable;
use library\Model\PlayInventoryTable;
use library\Model\PlayOrganizeraccountAuditTable;
use library\Model\PlayOrganizerCodeLogTable;
use library\Model\PlayPreMoneyLogTable;
use library\Model\PlayPreLogTable;
use library\Model\PlayInventoryLogTable;
use library\Model\PlayDistributionDetailTable;
use library\Model\PlayDistributionLogTable;
use library\Model\PlayUserAttachedTable;
use Zend\Db\Adapter\Adapter;


/**
 * 数据操作集合 只做调用对象操作
 * Class M
 * @package library\Fun
 */
class M
{

    //所有表操作放这里

    static private $baseModels = array();
    static private $MongoDB;

    static function SwooleSend($params)
    {
        $client = new \swoole_client(SWOOLE_SOCK_TCP);
        if (!$client->connect('127.0.0.1', 9502, 1)) {
            return 'swoole 服务错误 connect failed. Error: {$client->errCode}\n';
        } else {
            $client->send(json_encode($params));
            $msg = $client->recv();
            $client->close();
            return $msg;
        }
    }


    /**
     * mongoDb 链接
     * @return \MongoDB
     */
    static function _getMongoDB()
    {
        if (self::$MongoDB) {
            return self::$MongoDB;
        } else {
            $m = new \MongoClient('mongodb://127.0.0.1:27017');
            self::$MongoDB = $m->wft;

            return self::$MongoDB;
        }
    }

    /********************** mongoDb ********************/
    /**
     * @return \MongoCollection;
     */
    static function _getMdbSocialCircle()
    {
        return self::_getMongoDB()->social_circle;
    }

    /**
     * @return \MongoCollection;
     */
    static function _getMdbSocialCircleMsg()
    {
        return self::_getMongoDB()->social_circle_msg;
    }

    /**
     * 评分表
     * @return \MongoCollection;
     */
    static function _getMdbGradingRecords()
    {
        return self::_getMongoDB()->grading_records;
    }

    /**
     * @return \MongoCollection;
     */
    static function _getMdbSocialCircleMsgPost()
    {
        return self::_getMongoDB()->social_circle_msg_post;
    }


    /**
     * @return \MongoCollection;
     */
    static function _getMdbConsultPost()
    {
        return self::_getMongoDB()->consult_post;
    }


    /**
     * @return \MongoCollection;
     */
    static function _getMdbSocialChatMsg()
    {
        return self::_getMongoDB()->social_chat_msg;
    }

    /**
     * @return \MongoCollection;
     */
    static function _getMdbSocialFriends()
    {
        return self::_getMongoDB()->social_friends;
    }

    /**
     * @return \MongoCollection;
     */
    static function _getMdbSocialCircleUsers()
    {
        return self::_getMongoDB()->social_circle_users;
    }

    /**
     * @return \MongoCollection;
     */
    static function _getMdbSocialPrise()
    {
        return self::_getMongoDB()->social_prise;
    }

    static function _getMdbNearBy()
    {
        return self::_getMongoDB()->near_by;
    }

    /**
     * @return \MongoCollection;
     */

    static function _getMdbWeiPost()
    {
        return self::_getMongoDB()->wei_post;
    }

    /**
     * @return \MongoCollection;
     */

    static function _getMdbJoinTogether()
    {
        return self::_getMongoDB()->join_together;
    }

    /********************** mongoDb end ********************/


    /**
     * @return PlayClientUpdateTable
     */
    static function getPlayClientUpdateTable()
    {

        return ServiceManager::get('PlayClientUpdateTable');
    }

    /**
     * @return PlayPatchUpdateTable
     */
    static function getPlayPatchUpdateTable()
    {

        return ServiceManager::get('PlayPatchUpdateTable');
    }


    /**
     * @return PlayAdminCashTable;
     */
    static function getPlayAdminCashTable()
    {
        return ServiceManager::get('PlayAdminCashTable');


    }

    /**
     * @return PlayCashShareTable;
     */
    static function getPlayCashShareTable()
    {
        return ServiceManager::get('PlayCashShareTable');


    }

    /**
     * @return PlaySearchLogTable;
     */
    static function getPlaySearchLogTable()
    {
        return ServiceManager::get('PlaySearchLogTable');


    }

    /**
     * @return PrizeTable
     */
    static function getPrizeTableTable()
    {
        return ServiceManager::get('PrizeTable');


    }


    /**
     * @return PlaySearchFormValueTable;
     */
    static function getPlaySearchFormValueTable()
    {
        return ServiceManager::get('PlaySearchFormValueTable');


    }

    /**
     * @return PrizeUserDataTable
     */
    static function getPrizeUserDataTable()
    {
        return ServiceManager::get('PrizeUserDataTable');


    }

    /**
     * @return PrizeLogTable
     */
    static function getPrizeLogTable()
    {
        return ServiceManager::get('PrizeLogTable');


    }

    /**
     * @return mixed
     */
    static function getPlayAttachTable()
    {
        return ServiceManager::get('PlayAttachTable');


    }

    /**
     * @return PlaySettingsTable;
     */
    static function getPlaySettingsTable()
    {
        return ServiceManager::get('PlaySettingsTable');


    }

    /**
     * @return PlayLikeTable;
     */
    static function getPlayLikeTable()
    {
        return ServiceManager::get('PlayLikeTable');


    }

    /**
     * @return PlaySignInTable;
     */
    static function getPlaySignInTable()
    {
        return ServiceManager::get('PlaySignInTable');


    }

    /**
     * @return PlayAuthCodeTable
     */
    static function getPlayAuthCodeTable()
    {
        return ServiceManager::get('PlayAuthCodeTable');


    }

    /**
     * @return PlayCouponCodeTable;
     */
    static function getPlayCouponCodeTable()
    {
        return ServiceManager::get('PlayCouponCodeTable');


    }

    /**
     * @return PlayCouponsTable
     */
    static function getPlayCouponsTable()
    {
        return ServiceManager::get('PlayCouponsTable');


    }

    static function getPlayMarketTable()
    {
        return ServiceManager::get('PlayMarketTable');


    }

    static function getPlayInviteContentTable()
    {
        return ServiceManager::get('PlayInviteContentTable');


    }

    /**
     * @return PlayGoodCommentTable;
     */
    static function getPlayGoodCommentTable()
    {
        return ServiceManager::get('PlayGoodCommentTable');


    }

    static function getPlayTaskIntegralTable()
    {
        return ServiceManager::get('PlayTaskIntegralTable');


    }

    /**
     * @return PlayMarketSettingTable;
     */
    static function getPlayMarketSettingTable()
    {
        return ServiceManager::get('PlayMarketSettingTable');


    }

    /**
     * @return PlayOrderInfoTable;
     */
    static function getPlayOrderInfoTable()
    {
        return ServiceManager::get('PlayOrderInfoTable');


    }

    /**
     * @return PlayOrderActionTable;
     */
    static function getPlayOrderActionTable()
    {
        return ServiceManager::get('PlayOrderActionTable');


    }

    /**
     * @return PlayRegionTable;
     */
    static function getPlayRegionTable()
    {
        return ServiceManager::get('PlayRegionTable');


    }

    /**
     * @return PlayShopTable;
     */
    static function getPlayShopTable()
    {
        return ServiceManager::get('PlayShopTable');


    }

    /**
     * @return PlayUserTable;
     */
    static function getPlayUserTable()
    {
        return ServiceManager::get('PlayUserTable');


    }

    /**
     * @return PlayUserWeiXinTable;
     */
    static function getPlayUserWeiXinTable()
    {
        return ServiceManager::get('PlayUserWeiXinTable');


    }

    /**
     * @return  PlayUserLinkerTable;
     */
    static function getPlayUserLinkerTable()
    {
        return ServiceManager::get('PlayUserLinkerTable');


    }

    /**
     * @return  PlayUserAssociatesTable;
     */
    static function getPlayUserAssociatesTable()
    {
        return ServiceManager::get('PlayUserAssociatesTable');


    }

    /**
     * @return  PlayOrderInsureTable;
     */
    static function getPlayOrderInsureTable()
    {
        return ServiceManager::get('PlayOrderInsureTable');


    }

    /**
     * @return WeixinDituiLogTable
     */
    static function getWeixinDituiLogTable()
    {
        return ServiceManager::get('WeixinDituiLogTable');


    }

    /**
     * @return WeixinMenuTable
     */
    static function getWeixinMenuTable()
    {
        return ServiceManager::get('WeixinMenuTable');


    }

    /**
     * @return PlayCouponsLinkerTable;
     */
    static function getPlayCouponsLinkerTable()
    {
        return ServiceManager::get('PlayCouponsLinkerTable');


    }


    static function getPlayFeedbackTable()
    {
        return ServiceManager::get('PlayFeedbackTable');


    }

    /**
     * @return PlayShareTable;
     */
    static function getPlayShareTable()
    {
        return ServiceManager::get('PlayShareTable');


    }

    /**
     * @return PlayActivityTable;
     */
    static function getPlayActivityTable()
    {
        return ServiceManager::get('PlayActivityTable');


    }

    static function getPlayActivityTagTable()
    {
        return ServiceManager::get('PlayActivityTagTable');


    }

    /**
     * @return PlayPostTable;
     */
    static function getPlayPostTable()
    {
        return ServiceManager::get('PlayPostTable');


    }


    /**
     * @return PlayActivityCouponTable
     */
    static function getPlayActivityCouponTable()
    {
        return ServiceManager::get('PlayActivityCouponTable');


    }

    static function getPlayNewsTable()
    {
        return ServiceManager::get('PlayNewsTable');


    }


    /**
     * @return PlayClickLogTable;
     */
    static function getPlayClickLogTable()
    {
        return ServiceManager::get('PlayClickLogTable');


    }

    static function getPlayRegionLinkerTable()
    {
        return ServiceManager::get('PlayRegionLinkerTable');


    }

    /**
     * @return PlayIndexBlockTable
     */
    static function getPlayIndexBlockTable()
    {
        return ServiceManager::get('PlayIndexBlockTable');


    }

    /**
     * @return PlayIndexNewTable
     */
    static function getPlayIndexNewTable()
    {
        return ServiceManager::get('PlayIndexNewTable');


    }

    static function getPlayFocusMapTable()
    {
        return ServiceManager::get('PlayFocusMapTable');


    }

    static function getPlayMaBaoBaoTable()
    {
        return ServiceManager::get('PlayMaBaoBaoTable');


    }

    /**
     * @return PlayTalkStoryTable
     */
    static function getPlayTalkStoryTable()
    {
        return ServiceManager::get('PlayTalkStoryTable');


    }

    /**
     * @return PlayTalkPriseTable
     */
    static function getPlayTalkPriseTable()
    {
        return ServiceManager::get('PlayTalkPriseTable');


    }

    /**
     * @return PlayLabelTable;
     */
    static function getPlayLabelTable()
    {
        return ServiceManager::get('PlayLabelTable');


    }

    /**
     * @return PlayLabelTableMain;
     */
    static function getPlayLabelMainTable()
    {
        return ServiceManager::get('PlayLabelMainTable');


    }

    /**
     * @return PlayLabelLinkerTable;
     */
    static function getPlayLabelLinkerTable()
    {
        return ServiceManager::get('PlayLabelLinkerTable');


    }

    /**
     * @return PlayMessageLogTable;
     */
    static function getPlayMessageLogTable()
    {
        return ServiceManager::get('PlayMessageLogTable');


    }


    /**
     * @return PlayOrganizerTable;
     */
    static function getPlayOrganizerTable()
    {
        return ServiceManager::get('PlayOrganizerTable');


    }

    /**
     * @return PlayOrganizerGameTable;
     */
    static function getPlayOrganizerGameTable()
    {
        return ServiceManager::get('PlayOrganizerGameTable');


    }

    /**
     * @return PlayOrderOtherDataTable;
     */
    static function getPlayOrderOtherDataTable()
    {
        return ServiceManager::get('PlayOrderOtherDataTable');


    }

    /**
     * @return PlayGameInfoTable;
     */
    static function getPlayGameInfoTable()
    {
        return ServiceManager::get('PlayGameInfoTable');


    }

    /**
     * @return PlayGameTimeTable;
     */
    static function getPlayGameTimeTable()
    {
        return ServiceManager::get('PlayGameTimeTable');


    }

    /**
     * @return PlayGamePriceTable;
     */
    static function getPlayGamePriceTable()
    {
        return ServiceManager::get('PlayGamePriceTable');


    }

    /**
     * @return PlayOrderInfoGameTable;
     */
    static function getPlayOrderInfoGameTable()
    {
        return ServiceManager::get('PlayOrderInfoGameTable');


    }

    /**
     * @return PlayGameTagTable;
     */
    static function getPlayGameTagTable()
    {
        return ServiceManager::get('PlayGameTagTable');


    }

    /**
     * @return PlayUserCollectTable;
     */
    static function getPlayUserCollectTable()
    {
        return ServiceManager::get('PlayUserCollectTable');


    }

    /**
     * @return PlayUserMessageTable;
     */
    static function getPlayUserMessageTable()
    {
        return ServiceManager::get('PlayUserMessageTable');


    }

    /**
     * @return PlayUserBabyTable;
     */
    static function getPlayUserBabyTable()
    {
        return ServiceManager::get('PlayUserBabyTable');


    }


    /**
     * @return WeiXinReplyKeywordTable;
     */
    static function getWeiXinReplyKeyword()
    {
        return ServiceManager::get('WeiXinReplyKeywordTable');


    }

    /**
     * @return WeiXinReplyContentTable;
     */
    static function getWeiXinReplyContent()
    {
        return ServiceManager::get('WeiXinReplyContentTable');


    }

    /**
     * @return PlayMessagePushTable;
     */
    static function getPlayMessagePushTable()
    {
        return ServiceManager::get('PlayMessagePushTable');


    }

    /**
     * @return PlayPushTable;
     */
    static function getPlayPushTable()
    {
        return ServiceManager::get('PlayPushTable');


    }

    /**
     * @return PlayLinkOrganizerShopTable;
     */
    static function getPlayLinkOrganizerShopTable()
    {
        return ServiceManager::get('PlayLinkOrganizerShopTable');


    }

    /**
     * @return PlayOrganizerTouchTable;
     */
    static function getPlayOrganizerTouchTable()
    {
        return ServiceManager::get('PlayOrganizerTouchTable');


    }

    /**
     * @return ActivitySnoopyOrderTable;
     */
    static function getActivitySnoopyOrderTable()
    {
        return ServiceManager::get('ActivitySnoopyOrderTable');


    }

    //add kylin
    /**
     * @return InviteTokenTable;
     */
    static function getInviteToken()
    {
        return ServiceManager::get('InviteTokenTable');


    }

    /**
     * @return InviteRuleTable;
     */
    static function getInviteRule()
    {
        return ServiceManager::get('InviteRule');


    }

    /**
     * @return InviteRuleLogTable;
     */
    static function getInviteRuleLog()
    {
        return ServiceManager::get('InviteRuleLog');


    }

    /**
     * @return InviteMemberTable;
     */
    static function getInviteMember()
    {
        return ServiceManager::get('InviteMember');


    }

    /**
     * @return InviteRecieverAwardLogTable;
     */
    static function getInviteRecieverAwardLog()
    {
        return ServiceManager::get('InviteRecieverAwardLog');


    }

    /**
     * @return InviteInviterAwardLogTable;
     */
    static function getInviteInviterAwardLog()
    {
        return ServiceManager::get('InviteInviterAwardLog');


    }


    /**
     * @return AppGameCouponAddscorelogTable;
     */
    static function getAppGameCouponAddscorelogTable()
    {
        return ServiceManager::get('AppGameCouponAddscorelogTable');


    }


    /**
     * @return ActivitySnoopyVerifyCodeTable;
     */
    static function getActivitySnoopyVerifyCodeTable()
    {
        return ServiceManager::get('ActivitySnoopyVerifyCodeTable');


    }

    /**
     * @return ActivityBabygogogoBatchTable
     */
    static function getActivityBabygogogoBatchTable()
    {
        return ServiceManager::get('ActivityBabygogogoBatchTable');


    }

    /**
     * @return ActivityBabygogogoUserinfoTable;
     */
    static function getActivityBabygogogoUserinfoTable()
    {
        return ServiceManager::get('ActivityBabygogogoUserinfoTable');


    }

    /**
     * @return ActivityYouyouVerifyCodeTable;
     */
    static function getActivityYouyouVerifyCodeTable()
    {
        return ServiceManager::get('ActivityYouyouVerifyCodeTable');


    }


    /**
     * @return PlayGameAttributeLinkTable
     */
    static function getPlayGameAttributeLinkTable()
    {
        return ServiceManager::get('PlayGameAttributeLinkTable');


    }

    /**
     * @return PlayGameAttributeTable;
     */
    static function getPlayGameAttributeTable()
    {
        return ServiceManager::get('PlayGameAttributeTable');


    }

    /**
     * @return SocialCircleUsersTable;
     */
    static function getSocialCircleUsersTable()
    {
        return ServiceManager::get('SocialCircleUsersTable');


    }

    /**
     * @return SocialCircleTable;
     */
    static function getSocialCircleTable()
    {
        return ServiceManager::get('SocialCircleTable');


    }

    /**
     * @return SocialCircleMsgTable;
     */
    static function getSocialCircleMsgTable()
    {
        return ServiceManager::get('SocialCircleMsgTable');


    }

    /**
     * @return SocialCircleMsgPostTable;
     */
    static function getSocialCircleMsgPostTable()
    {
        return ServiceManager::get('SocialCircleMsgPostTable');


    }

    /**
     * @return SocialChatMsgTable;
     */
    static function getSocialChatMsgTable()
    {
        return ServiceManager::get('SocialChatMsgTable');


    }

    /**
     * @return SocialCircleTable;
     */
    static function getSocialFriendsTable()
    {
        return ServiceManager::get('SocialFriend');


    }

    /**
     * @return PlayWebActivityTable;
     */
    static function getPlayWebActivityTable()
    {
        return ServiceManager::get('PlayWebActivityTable');


    }

    /**
     * @return PlayGroupBuyTable;
     */
    static function getPlayGroupBuyTable()
    {
        return ServiceManager::get('PlayGroupBuyTable');


    }

    /**
     * @return playAdminWorkLogTable;
     */
    static function getPlayAdminWorkLogTable()
    {
        return ServiceManager::get('PlayAdminWorkLogTable');


    }


    /**
     * @return PlayAccountTable;
     */
    static function getPlayAccountTable()
    {
        return ServiceManager::get('PlayAccountTable');


    }

    /**
     * @return PlayAccountLogTable;
     */
    static function getPlayAccountLogTable()
    {
        return ServiceManager::get('PlayAccountLogTable');


    }


    /**
     * @return playTagsTable;
     */
    static function getPlayTagsTable()
    {
        return ServiceManager::get('PlayTagsTable');


    }

    /**
     * @return playTagsLinkTable;
     */
    static function getPlayTagsLinkTable()
    {
        return ServiceManager::get('PlayTagsLinkTable');


    }

    /**
     * @return playNearbyTable;
     */
    static function getPlayNearbyTable()
    {
        return ServiceManager::get('PlayNearbyTable');


    }

    /**
     * @return mixed
     */
    static function getAuthAccessTable()
    {
        return ServiceManager::get('AuthAccessTable');


    }

    /**
     * @return mixed
     */
    static function getAuthGroupTable()
    {
        return ServiceManager::get('AuthGroupTable');


    }

    /**
     * @return AuthMenuTable
     */
    static function getAuthMenuTable()
    {
        return ServiceManager::get('AuthMenuTable');


    }

    /**
     * @return CashCouponTable;
     */
    static function getCashCouponTable()
    {
        return ServiceManager::get('CashCouponTable');


    }

    /**
     * @return CashCouponGoodTable
     */
    static function getCashCouponGoodTable()
    {
        return ServiceManager::get('CashCouponGoodTable');


    }

    /**
     * @return CashCouponUserTable
     */
    static function getCashCouponUserTable()
    {
        return ServiceManager::get('CashCouponUserTable');


    }


    /**
     * @return IntegralUserTable;
     */
    static function getPlayIntegralUserTable()
    {
        return ServiceManager::get('IntegralUserTable');


    }

    /**
     * @return QualifyTable
     */
    static function getQualifyTable()
    {
        return ServiceManager::get('QualifyTable');


    }

    /**
     * @return CashCouponCityTable
     */
    static function getCashCouponCityTable()
    {
        return ServiceManager::get('CashCouponCityTable');


    }

    static function getStationTable()
    {
        return ServiceManager::get('StationTable');


    }

    static function getPlayCityTable()
    {
        return ServiceManager::get('PlayCityTable');


    }

    /**
     * @return PlayShopStrategyTable;
     */
    static function getPlayShopStrategyTable()
    {
        return ServiceManager::get('PlayShopStrategyTable');


    }

    /**
     * @return PlayWelfareIntegralTable;
     */
    static function getPlayWelfareIntegralTable()
    {
        return ServiceManager::get('PlayWelfareIntegralTable');


    }

    /**
     * @return PlayWelfareRebateTable;
     */
    static function getPlayWelfareRebateTable()
    {
        return ServiceManager::get('PlayWelfareRebateTable');


    }

    /**
     * @return PlayWelfareCashTable;
     */
    static function getPlayWelfareCashTable()
    {
        return ServiceManager::get('PlayWelfareCashTable');


    }

    /**
     * @return PlayIntegralTable;
     */
    static function getPlayIntegralTable()
    {
        return ServiceManager::get('PlayIntegralTable');


    }

    /**
     * @return PlayContractsTable;
     */
    static function getPlayContractsTable()
    {
        return ServiceManager::get('PlayContractsTable');


    }

    /**
     * @return PlayContractsLinkTable;
     */
    static function getPlayContractsLinkTable()
    {
        return ServiceManager::get('PlayContractsLinkTable');


    }


    /**
     * @return PlayContractsPriceTable;
     */
    static function getPlayContractsPriceTable()
    {
        return ServiceManager::get('PlayContractsPriceTable');


    }

    /**
     * @return PlayCodeUsedTable;
     */
    static function getPlayCodeUsedTable()
    {
        return ServiceManager::get('PlayCodeUsedTable');


    }

    /**
     * @return PlayBusinessGroupTable;
     */
    static function getPlayBusinessGroupTable()
    {
        return ServiceManager::get('PlayBusinessGroupTable');


    }


    /**
     * @return PlayWelfareTable;
     */
    static function getPlayWelfareTable()
    {
        return ServiceManager::get('PlayWelfareTable');

    }


    /**
     * @return PlayPrivatePartyTable;
     */
    static function getPlayPrivatePartyTable()
    {
        return ServiceManager::get('PlayPrivatePartyTable');

    }


    /**
     * @return PlayContractActionTable;
     */
    static function getPlayContractActionTable()
    {
        return ServiceManager::get('PlayContractActionTable');

    }

    /**
     * @return PlayOrganizerAccountTable;
     */
    static function getPlayOrganizerAccountTable()
    {
        return ServiceManager::get('PlayOrganizerAccountTable');

    }

    /**
     * @return PlayOrganizerAccountLogTable;
     */
    static function getPlayOrganizerAccountLogTable()
    {
        return ServiceManager::get('PlayOrganizerAccountLogTable');

    }

    /**
     * @return PlayContractsLinkUsedTable;
     */
    static function getPlayContractsLinkUsedTable()
    {
        return ServiceManager::get('PlayContractsLinkUsedTable');

    }

    /**
     * @return PlayContractLinkGoodTable;
     */
    static function getPlayContractLinkGoodTable()
    {
        return ServiceManager::get('PlayContractLinkGoodTable');

    }

    /**
     * @return PlayContractLinkPriceTable;
     */
    static function getPlayContractLinkPriceTable()
    {
        return ServiceManager::get('PlayContractLinkPriceTable');

    }

    //v3.3
    /**
     * @return PlayExcerciseBaseTable;
     */
    static function getPlayExcerciseBaseTable()
    {
        return ServiceManager::get('PlayExcerciseBaseTable');

    }

    /**
     * @return PlayExcerciseMeetingTable;
     */
    static function getPlayExcerciseMeetingTable()
    {
        return ServiceManager::get('PlayExcerciseMeetingTable');

    }

    /**
     * @return PlayExcerciseEventTable;
     */
    static function getPlayExcerciseEventTable()
    {
        return ServiceManager::get('PlayExcerciseEventTable');

    }

    /**
     * @return PlayExcerciseCodeTable;
     */
    static function getPlayExcerciseCodeTable()
    {
        return ServiceManager::get('PlayExcerciseCodeTable');

    }

    /**
     * @return PlayExcercisePriceTable
     */
    static function getPlayExcercisePriceTable()
    {
        return ServiceManager::get('PlayExcercisePriceTable');

    }


    /**
     * @return PlayExcerciseScheduleTable
     */
    static function getPlayExcerciseScheduleTable()
    {
        return ServiceManager::get('PlayExcerciseScheduleTable');

    }

    /**
     * @return PlayExcerciseShopTable
     */
    static function getPlayExcerciseShopTable()
    {
        return ServiceManager::get('PlayExcerciseShopTable');

    }

    /**
     * @return AwardLogTable
     */
    static function getAwardLogTable()
    {
        return ServiceManager::get('AwardLogTable');


    }

    /**
     * @return AwardTable
     */
    static function getAwardTable()
    {
        return ServiceManager::get('AwardTable');


    }

    /**
     * @return PlayGroundTable
     */
    static function getPlayGroundTable()
    {
        return ServiceManager::get('PlayGroundTable');

    }

    /**
     * @return PlayInventoryTable
     */
    static function getPlayInventoryTable()
    {
        return ServiceManager::get('PlayInventoryTable');

    }

    /**
     * @return PlayOrganizeraccountAuditTable
     */
    static function getPlayOrganizeraccountAuditTable()
    {
        return ServiceManager::get('PlayOrganizeraccountAuditTable');

    }

    /**
     * @return PlayPreMoneyLogTable
     */
    static function getPlayPreMoneyLogTable()
    {
        return ServiceManager::get('PlayPreMoneyLogTable');

    }

    /**
     * @return PlayPreLogTable
     */
    static function getPlayPreLogTable()
    {
        return ServiceManager::get('PlayPreLogTable');

    }

    /**
     * @return PlayInventoryLogTable
     */
    static function getPlayInventoryLogTable()
    {
        return ServiceManager::get('PlayInventoryLogTable');

    }

    /**
     * @return PlayOrganizerCodeLogTable
     */
    static function getPlayOrganizerCodeLogTable()
    {
        return ServiceManager::get('PlayOrganizerCodeLogTable');

    }

    /**
     * @return PlayDistributionDetailTable
     */
    static function getPlayDistributionDetailTable()
    {
        return ServiceManager::get('PlayDistributionDetailTable');

    }

    /**
     * @return PlayDistributionLogTable
     */
    static function getPlayDistributionLogTable()
    {
        return ServiceManager::get('PlayDistributionLogTable');

    }

    /**
     * @return PlayAdminTable;
     */
    static function getPlayAdminTable()
    {
        return ServiceManager::get('PlayAdminTable');
    }

    /**
<<<<<<< HEAD
     * @return PlayMemberTable;
     */
    static function getPlayMemberTable()
    {
        return ServiceManager::get('PlayMemberTable');
    }

    /**
     * @return PlayMemberMoneyServiceTable;
     */
    static function getPlayMemberMoneyServiceTable()
    {

        return ServiceManager::get('PlayMemberMoneyServiceTable');
    }

    /**
     * @return PlayShareDataTable
     */
    static function getPlayShareDataTable()
    {

        return ServiceManager::get('PlayShareDataTable');
    }

    /**
     * @return PlayBannerTable
     */
    static function getPlayBannerTable()
    {

        return ServiceManager::get('PlayBannerTable');
    }

    /**
     * @return PlayBannerSettingTable
     */
    static function getPlayBannerSettingTable()
    {
        return ServiceManager::get('PlayBannerSettingTable');
    }

    /**
     * @return PlayUserAttachedTable;
     */
    static function getPlayUserAttachedTable()
    {
        return ServiceManager::get('PlayUserAttachedTable');

    }

    /**
     * 带缓存的查询
     * @param string $sql
     * @param array $prepare
     * @param int $cache_ttl
     * @param string $cache_key
     * @return bool|array
     */
    static function queryCache($sql = '', $prepare = array(), $cache_ttl = 5,$cache_key = '')
    {
        return ServiceManager::get('Db')->queryCache($sql, $prepare, $cache_ttl,$cache_key);
    }

    /**
     * @return Adapter
     */
    static function getAdapter()
    {
        return ServiceManager::get('Db')->getAdapter();
    }


    /**
     * 获取表基础模型
     * @param $tablename
     * @return BaseTable
     * @throws \Exception
     */
    static function BaseModel($tablename)
    {
        if ($tablename) {
            if (!array_key_exists($tablename, self::$baseModels)) {
                self::$baseModels[$tablename] = new BaseTable($tablename);
            }
            return self::$baseModels[$tablename];
        } else {
            throw new \Exception("表名不能为空1");
        }
    }




}