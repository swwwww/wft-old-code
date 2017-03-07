<?php
namespace library\Service\System\Cache;

//定义各种缓存名称和缓存时间
class KeyNames
{

    //详情
    const KEYNAME = "";
    const KEYNAME_TTL = 10;

    //用户
    const USERINFO_TTL = 20; //mysql 用户数据


    //自动退款 code
    const REFUND_CODE = "D:refund";
    const REFUND_CODE_TTL = 43200;

    //咨询数量更新时间
    const CONSULT_REPLY = "D:consult";
    const CONSULT_REPLY_TTL = 900;

    //订单
    const CANCEL_ORDER_LIST = 'cancel_order_list'; //取消订单的队列
    const CANCEL_ORDER_ID = 'cancel_order_id'; //取消订单的队列


    //其他
    const WFT_STR_SHAREDATA_KEY           = "D:ShareData";
    const WFT_STR_MAX_PRICE_MONEY_SERVICE = "D:MaxPriceMoneyService";
    const WFT_STR_BANNER_LIST             = "D:BannerList";
    const WFT_STR_BANNER_SETTING_LIST     = "D:BannerSettingList";
    const WFT_STR_BANNER                  = "D:Banner:";
    const WFT_STR_BANNER_SETTING          = "D:BannerSetting:";
    const WFT_STR_ORDER_INFO              = "D:OrderInfo:";
}