<?php
return [
    //日志
    'Db' => function ($sm) {
        return new \library\Service\System\Db\Db();
    },

    'PlayAdminTable' => function ($sm) {
        return new \library\Model\PlayAdminTable('play_admin');
    },

    'PlayGroundTable' => function ($sm) {
        return new \library\Model\PlayGroundTable('play_ground');
    },

    'PlayAdminCashTable' => function ($sm) {
        return new \library\Model\PlayAdminCashTable('play_admin_cash');
    },
    'PlayOrderInsureTable' => function ($sm) {
        return new \library\Model\PlayOrderInsureTable('play_order_insure');
    },
    'PlayInviteContentTable' => function ($sm) {
        return new \library\Model\PlayInviteContentTable('play_invite_content');
    },

    'PlayGoodCommentTable' => function ($sm) {
        return new \library\Model\PlayGoodCommentTable('play_good_comment');
    },
    'PlayPostTable' => function ($sm) {
        return new \library\Model\PlayPostTable('play_post');
    },

    'PlayAccountTable' => function ($sm) {
        return new \library\Model\PlayAccountTable('play_account');
    },

    'PlayAccountLogTable' => function ($sm) {
        return new \library\Model\PlayAccountLogTable('play_account_log');
    },

    'PlaySearchFormValueTable' => function ($sm) {
        return new \library\Model\PlaySearchFormValueTable('play_search_form_value');
    },

    'PlaySettingsTable' => function ($sm) {
        return new \library\Model\PlaySettingsTable('play_settings');
    },

    'PrizeTable' => function ($sm) {
        return new \library\Model\PrizeTable('play_prize');
    },

    'PrizeUserDataTable' => function ($sm) {
        return new \library\Model\PrizeUserDataTable('PrizeUserDataTable');
    },

    'PrizeLogTable' => function ($sm) {
        return new \library\Model\PrizeLogTable('play_prize_log');
    },

    'PlayMessageLogTable' => function ($sm) {
        return new \library\Model\PlayMessageLogTable('play_message_log');
    },
    'PlayCouponsLinkerTable' => function ($sm) {
        return new \library\Model\PlayCouponsLinkerTable('play_coupons_linker');
    },

    'PlayUserLinkerTable' => function ($sm) {
        return new \library\Model\PlayUserLinkerTable('play_user_linker');
    },

    'PlayAuthCodeTable' => function ($sm) {
        return new \library\Model\PlayAuthCodeTable('play_auth_code');
    },

    'PlayUserTable' => function ($sm) {
        return new \library\Model\PlayUserTable('play_user');
    },

    'PlayUserWeiXinTable' => function ($sm) {
        return new \library\Model\PlayUserWeiXinTable('play_user_weixin');
    },

    'PlayOrderInfoTable' => function ($sm) {
        return new \library\Model\PlayOrderInfoTable('play_order_info');
    },

    'PlayOrderActionTable' => function ($sm) {
        return new \library\Model\PlayOrderActionTable('play_order_action');
    },

    'PlayCouponCodeTable' => function ($sm) {
        return new \library\Model\PlayCouponCodeTable('play_coupon_code');
    },

    'PlayFeedbackTable' => function ($sm) {
        return new \library\Model\PlayFeedbackTable('play_feedback');
    },
    'PlayMarketTable' => function ($sm) {
        return new \library\Model\PlayMarketTable('play_market');
    },
    'PlayMarketSettingTable' => function ($sm) {
        return new \library\Model\PlayMarketSettingTable('play_market_setting');
    },

    'PlayShopTable' => function ($sm) {
        return new \library\Model\PlayShopTable('play_shop');
    },
    'PlayRegionTable' => function ($sm) {
        return new \library\Model\PlayRegionTable('play_region');
    },

    'PlayShareTable' => function ($sm) {
        return new \library\Model\PlayShareTable('play_share');
    },
    'PlayCouponsTable' => function ($sm) {
        return new \library\Model\PlayCouponsTable('play_coupons');
    },

    'PlayAttachTable' => function ($sm) {
        return new \library\Model\PlayAttachTable('play_attach');
    },
    'PlayCouponLinkerTable' => function ($sm) {
        return new \library\Model\PlayCouponsLinkerTable('play_coupons_linker');
    },

    'PlayActivityTable' => function ($sm) {
        return new \library\Model\PlayActivityTable('play_activity');
    },

    'PlayActivityTagTable' => function ($sm) {
        return new \library\Model\PlayActivityTagTable('play_activity_tag');
    },

    'PlayActivityCouponTable' => function ($sm) {
        return new \library\Model\PlayActivityCouponTable('play_activity_coupon');
    },

    'PlayNewsTable' => function ($sm) {
        return new \library\Model\PlayNewsTable('play_news');
    },

    'PlayRegionLinkerTable' => function ($sm) {
        return new \library\Model\PlayRegionLinkerTable('play_region_linker');
    },

    'PlayIndexBlockTable' => function ($sm) {
        return new \library\Model\PlayIndexBlockTable('play_index_block');
    },

    'PlayFocusMapTable' => function ($sm) {
        return new \library\Model\PlayFocusMapTable('play_focus_map');
    },

    'PlayMaBaoBaoTable' => function ($sm) {
        return new \library\Model\PlayMaBaoBaoTable('play_mabaobao');
    },

    'PlayTalkStoryTable' => function ($sm) {
        return new \library\Model\PlayTalkStoryTable('activity_talk_story');
    },

    'PlayTalkPriseTable' => function ($sm) {
        return new \library\Model\PlayTalkPriseTable('activity_talk_prise');
    },

    'PlayLabelTable' => function ($sm) {
        return new \library\Model\PlayLabelTable('play_label');
    },

    'PlayLabelMainTable' => function ($sm) {
        return new \library\Model\PlayLabelMainTable('play_label_main');
    },

    'PlayLabelLinkerTable' => function ($sm) {
        return new \library\Model\PlayLabelLinkerTable('play_label_linker');
    },

    'PlayOrganizerTable' => function ($sm) {
        return new \library\Model\PlayOrganizerTable('play_organizer');
    },

    'PlayOrganizerGameTable' => function ($sm) {
        return new \library\Model\PlayOrganizerGameTable('play_organizer_game');
    },

    'PlayGameInfoTable' => function ($sm) {
        return new \library\Model\PlayGameInfoTable('play_game_info');
    },

    'PlayGameTimeTable' => function ($sm) {
        return new \library\Model\PlayGameTimeTable('play_game_time');
    },

    'PlayGamePriceTable' => function ($sm) {
        return new \library\Model\PlayGamePriceTable('play_game_price');
    },

    'PlayGameTagTable' => function ($sm) {
        return new \library\Model\PlayGameTagTable('play_game_tag');
    },

    'PlayOrderInfoGameTable' => function ($sm) {
        return new \library\Model\PlayOrderInfoGameTable('play_order_info_game');
    },

    'WeiXinReplyContentTable' => function ($sm) {
        return new \library\Model\WeiXinReplyContentTable('weixin_reply_content');
    },

    'WeiXinReplyKeywordTable' => function ($sm) {
        return new \library\Model\WeiXinReplyKeywordTable('weixin_reply_keyword');
    },

    'PlayUserCollectTable' => function ($sm) {
        return new \library\Model\PlayUserCollectTable('play_user_collect');
    },

    'PlayUserMessageTable' => function ($sm) {
        return new \library\Model\PlayUserMessageTable('play_user_message');
    },

    'PlayUserBabyTable' => function ($sm) {
        return new \library\Model\PlayUserBabyTable('play_user_baby');
    },

    'PlayMessagePushTable' => function ($sm) {
        return new \library\Model\PlayMessagePushTable('play_message_push');
    },

    'PlayPushTable' => function ($sm) {
        return new \library\Model\PlayPushTable('play_push');
    },

    'PlayLinkOrganizerShopTable' => function ($sm) {
        return new \library\Model\PlayLinkOrganizerShopTable('play_linker_organizer_shop');
    },

    'PlayOrganizerTouchTable' => function ($sm) {
        return new \library\Model\PlayOrganizerTouchTable('play_organizer_touch');
    },

    'ActivitySnoopyOrderTable' => function ($sm) {
        return new \library\Model\ActivitySnoopyOrderTable('activity_snoopy_order');
    },

    'ActivitySnoopyVerifyCodeTable' => function ($sm) {
        return new \library\Model\ActivitySnoopyVerifyCodeTable('activity_snoopy_verify_code');
    },

    'PlayGameAttributeLinkTable' => function ($sm) {
        return new \library\Model\PlayGameAttributeLinkTable('play_game_attribute_link');
    },

    'PlayClickLogTable' => function ($sm) {
        return new \library\Model\PlayClickLogTable('play_click_log');
    },

    'PlayGameAttributeTable' => function ($sm) {
        return new \library\Model\PlayGameAttributeTable('play_game_attribute');
    },

    'SocialCircleUsersTable' => function ($sm) {
        return new \library\Model\SocialCircleUsersTable('social_circle_users');
    },

    'PlayWebActivityTable' => function ($sm) {
        return new \library\Model\PlayWebActivityTable('play_web_activity');
    },

    'WeixinDituiLogTable' => function ($sm) {
        return new \library\Model\WeixinDituiLogTable('weixin_ditui_log');
    },

    'WeixinMenuTable' => function ($sm) {
        return new \library\Model\WeixinMenuTable('weixin_menu');
    },

    'ActivityBabygogogoBatchTable' => function ($sm) {
        return new \library\Model\ActivityBabygogogoBatchTable('activity_babygogogo_batch');
    },

    'ActivityBabygogogoUserinfoTable' => function ($sm) {
        return new \library\Model\ActivityBabygogogoUserinfoTable('activity_babygogogo_userinfo');
    },

    'ActivityYouyouVerifyCodeTable' => function ($sm) {
        return new \library\Model\ActivityYouyouVerifyCodeTable('activity_youyou_verify_code');
    },
    'PlayGroupBuyTable' => function ($sm) {
        return new \library\Model\PlayGroupBuyTable('play_group_buy');
    },

    'PlayAdminWorkLogTable' => function ($sm) {
        return new \library\Model\PlayAdminWorkLogTable('play_admin_work_log');
    },

    'PlayTagsTable' => function ($sm) {
        return new \library\Model\PlayTagsTable('play_tags');
    },

    'PlayTagsLinkTable' => function ($sm) {
        return new \library\Model\PlayTagsLinkTable('play_tags_link');
    },

    'PlayNearbyTable' => function ($sm) {
        return new \library\Model\PlayNearbyTable('play_nearby');
    },
    'AuthMenuTable' => function ($sm) {
        return new \library\Model\AuthMenuTable('play_auth_menu_rule');
    },
    'AuthAccessTable' => function ($sm) {
        return new \library\Model\AuthAccessTable('play_auth_group_access');
    },
    'AuthGroupTable' => function ($sm) {
        return new \library\Model\AuthGroupTable('play_auth_group');
    },
    'QualifyTable' => function ($sm) {
        return new \library\Model\QualifyTable('play_qualify_coupon');
    },

    'CashCouponCityTable' => function ($sm) {
        return new \library\Model\CashCouponCityTable('play_cashcoupon_city');
    },
    'CashCouponGoodTable' => function ($sm) {
        return new \library\Model\CashCouponGoodTable('play_cashcoupon_good_link');
    },
    'CashCouponUserTable' => function ($sm) {
        return new \library\Model\CashCouponUserTable('play_cashcoupon_user_link');
    },
    'CashCouponTable' => function ($sm) {
        return new \library\Model\CashCouponTable('play_cash_coupon');
    },
    'PlayCashShareTable' => function ($sm) {
        return new \library\Model\PlayCashShareTable('play_cash_share');
    },
    'PlayIntegralTable' => function ($sm) {
        return new \library\Model\PlayIntegralTable('play_integral');
    },
    'IntegralUserTable' => function ($sm) {
        return new \library\Model\IntegralUserTable('play_integral_user');
    },

    'StationTable' => function ($sm) {
        return new \library\Model\StationTable('play_station');
    },

    'PlayCityTable' => function ($sm) {
        return new \library\Model\PlayCityTable('play_city');
    },

    'PlayShopStrategyTable' => function ($sm) {
        return new \library\Model\PlayShopStrategyTable('play_shop_strategy');
    },

    'PlayWelfareIntegralTable' => function ($sm) {
        return new \library\Model\PlayWelfareIntegralTable('play_welfare_integral');
    },

    'PlayWelfareRebateTable' => function ($sm) {
        return new \library\Model\PlayWelfareRebateTable('play_welfare_rebate');
    },

    'PlayWelfareCashTable' => function ($sm) {
        return new \library\Model\PlayWelfareCashTable('play_welfare_cash');
    },

    'PlayTaskIntegralTable' => function ($sm) {
        return new \library\Model\PlayTaskIntegralTable('play_task_integral');
    },
    //add kylin
    'AppGameCouponAddscorelogTable' => function ($sm) {
        return new \library\Model\AppGameCouponAddscorelogTable('app_game_coupon_addscorelog');
    },
    //add qinyuan
    'PlayContractsLinkTable' => function ($sm) {
        return new \library\Model\PlayContractsLinkTable('play_contracts_link');
    },

    //add qinyuan
    'PlayContractsTable' => function ($sm) {
        return new \library\Model\PlayContractsTable('play_contracts');
    },

    //add qinyuan
    'PlayContractsPriceTable' => function ($sm) {
        return new \library\Model\PlayContractsPriceTable('play_contracts_price');
    },

    'InviteTokenTable' => function ($sm) {
        return new \library\Model\InviteTokenTable('invite_token');
    },

    'InviteRule' => function ($sm) {
        return new \library\Model\InviteRuleTable('invite_rule');
    },

    'InviteRuleLog' => function ($sm) {
        return new \library\Model\InviteRuleLogTable('invite_rule_log');
    },

    'InviteMember' => function ($sm) {
        return new \library\Model\InviteMemberTable('invite_member');
    },

    'InviteRecieverAwardLog' => function ($sm) {
        return new \library\Model\InviteRecieverAwardLogTable('invite_reciever_award_log');
    },

    'InviteInviterAwardLog' => function ($sm) {
        return new \library\Model\InviteInviterAwardLogTable('invite_inviter_award_log');
    },
    'PlayCodeUsedTable' => function ($sm) {
        return new \library\Model\PlayCodeUsedTable('play_code_used');
    },

    'PlayBusinessGroupTable' => function ($sm) {
        return new \library\Model\PlayBusinessGroupTable('play_business_group');
    },

    'PlayWelfareTable' => function ($sm) {
        return new \library\Model\PlayWelfareTable('play_welfare');
    },

    //add qinyuan
    'PlayContractActionTable' => function ($sm) {
        return new \library\Model\PlayContractActionTable('play_contract_action');
    },

    'PlayOrganizerAccountTable' => function ($sm) {
        return new \library\Model\PlayOrganizerAccountTable('play_organizer_account');
    },

    'PlayOrganizerAccountLogTable' => function ($sm) {
        return new \library\Model\PlayOrganizerAccountLogTable('play_organizer_account_log');
    },

    'PlayContractsLinkUsedTable' => function ($sm) {
        return new \library\Model\PlayContractsLinkUsedTable('play_contracts_link_used');
    },

    'PlayContractLinkGoodTable' => function ($sm) {
        return new \library\Model\PlayContractLinkGoodTable('play_contract_link_good');
    },

    'PlayContractLinkPriceTable' => function ($sm) {
        return new \library\Model\PlayContractLinkPriceTable('play_contract_link_price');
    },
    'AwardLogTable' => function ($sm) {
        return new \library\Model\PlayAwardLogTable('play_award_log');
    },
    'AwardTable' => function ($sm) {
        return new \library\Model\PlayAwardTable('play_award');
    },

    'PlaySearchLogTable' => function ($sm) {
        return new \library\Model\PlayContractLinkPriceTable('play_search_log');
    },

    'PlayOrderOtherDataTable' => function ($sm) {
        return new \library\Model\PlayOrderOtherDataTable('play_order_otherdata');
    },

    'PlayPrivatePartyTable' => function ($sm) {
        return new \library\Model\PlayPrivatePartyTable('play_private_party');
    },

    'PlayUserAssociatesTable' => function ($sm) {
        return new \library\Model\PlayUserAssociatesTable('play_user_associates');
    },

    //v3.3
    'PlayExcerciseBaseTable' => function ($sm) {
        return new \library\Model\PlayExcerciseBaseTable('play_excercise_base');
    },
    'PlayExcerciseMeetingTable' => function ($sm) {
        return new \library\Model\PlayExcerciseMeetingTable('play_excercise_meeting');
    },
    'PlayExcerciseEventTable' => function ($sm) {
        return new \library\Model\PlayExcerciseEventTable('play_excercise_event');
    },

    'PlayExcerciseCodeTable' => function ($sm) {
        return new \library\Model\PlayExcerciseCodeTable('play_excercise_code');
    },

    'PlayExcercisePriceTable' => function ($sm) {
        return new \library\Model\PlayExcercisePriceTable('play_excercise_price');
    },

    'PlayExcerciseScheduleTable' => function ($sm) {
        return new \library\Model\PlayExcerciseScheduleTable('play_excercise_schedule');
    },

    'PlayExcerciseShopTable' => function ($sm) {
        return new \library\Model\PlayExcerciseShopTable('play_excercise_shop');
    },

    'PlayInventoryTable' => function ($sm) {
        return new \library\Model\PlayInventoryTable('play_inventory');
    },

    'PlayOrganizeraccountAuditTable' => function ($sm) {
        return new \library\Model\PlayOrganizeraccountAuditTable('play_organizeraccount_audit');
    },

    'PlayPreMoneyLogTable' => function ($sm) {
        return new \library\Model\PlayPreMoneyLogTable('play_pre_money_log');
    },

    'PlayPreLogTable' => function ($sm) {
        return new \library\Model\PlayPreLogTable('play_pre_log');
    },

    'PlayInventoryLogTable' => function ($sm) {
        return new \library\Model\PlayInventoryLogTable('play_inventory_log');
    },

    'PlayOrganizerCodeLogTable' => function ($sm) {
        return new \library\Model\PlayOrganizerCodeLogTable('play_organizer_code_log');
    },

    'PlayDistributionDetailTable' => function ($sm) {
        return new \library\Model\PlayDistributionDetailTable('play_distribution_detail');
    },

    'PlayDistributionLogTable' => function ($sm) {
        return new \library\Model\PlayDistributionLogTable('play_distribution_log');
    },

    'PlayClientUpdateTable' => function ($sm) {
        return new \library\Model\PlayClientUpdateTable('play_client_update');
    },
    'PlayPatchUpdateTable' => function ($sm) {
        return new \library\Model\PlayPatchUpdateTable('play_patch_update');
    },

    'PlayMemberMoneyServiceTable' => function ($sm) {
        return new \library\Model\PlayMemberMoneyServiceTable('play_member_money_service');
    },
    'PlayMemberTable' => function ($sm) {
        return new \library\Model\PlayMemberTable('play_member');
    },
    'PlayShareDataTable' => function ($sm) {
        return new \library\Model\PlayShareDataTable('play_share_data');
    },
    'PlayBannerTable' => function ($sm) {
        return new \library\Model\PlayBannerTable('play_banner');
    },
    'PlayBannerSettingTable' => function ($sm) {
        return new \library\Model\PlayBannerSettingTable('play_banner_setting');
    },

    'PlayUserAttachedTable' => function ($sm) {
        return new \library\Model\PlayUserAttachedTable('play_user_attached');
    },


];