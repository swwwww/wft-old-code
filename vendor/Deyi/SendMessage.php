<?php
namespace Deyi;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\GeTui\GeTui;
use Application\Module;
use library\Fun\Common;
use library\Service\ServiceManager;
use library\Service\System\Cache\RedCache;

class SendMessage
{
    // 短信发送场景
    const MESSAGE_STATUS_PAY_SUCCESS = 1; // 支付成功
    const MESSAGE_STATUS_USE_SUCCESS = 2; // 兑换成功 / 验证成功 / 预约成功
    const MESSAGE_STATUS_USE_REMIND_REFUND = 3; // 订单提醒使用 （可退）
    const MESSAGE_STATUS_USE_REMIND_NOREFUND = 4; // 订单提醒使用 （不可退）
    const MESSAGE_STATUS_REFUND_REFUND = 5; // （用户发起）退款时提醒（可退）
    const MESSAGE_STATUS_REFUND_NOREFUND = 6; // （用户发起）退款时提醒（不可退）
    const MESSAGE_STATUS_ADMIN_REFUND = 7; // （后台发起）退款时提醒
    const MESSAGE_STATUS_REFUND_SUCCESS = 8; // 退款至支付账户
    const MESSAGE_STATUS_NOGROUP_REFUND = 9; // 未成团退款

    // 短信模板类型
    const MESSAGE_TYPE_NORMAL = 1;  // 通用
    const MESSAGE_TYPE_HOTAL = 2;  // 酒店类商品
    const MESSAGE_TYPE_RESERVE = 3;  // 预约使用类商品
    const MESSAGE_TYPE_ZYB = 4;  // 智游宝商品
    const MESSAGE_TYPE_GROUP = 5;  // 拼团
    const MESSAGE_TYPE_ACTIVITY = 6;  // 遛娃活动
    const MESSAGE_TYPE_MEITUAN = 10; // 美团合作

    public static $error = '';
    use JsonResponse;
    use BaseController;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    public static function getWeixinName($city)
    {
        if ($city == 'NJ') {
            return "Wanfantiannanjing";
        } else {//武汉
            return "wft20160301";
        }
    }

    /**
     * @param phone          要发送短信的号码
     * @param goods_name     商品名称 / 活动名称
     * @param game_name      套系名称
     * @param game_time      场次时间
     * @param buy_time       购买时间
     * @param use_time       游玩时间
     * @param end_time       截止时间
     * @param limit_number   团购人数
     * @param custom_content 自定义内容
     * @param price          付款金额
     * @param code           验证码
     * @param code_count     验证码数量
     * @param zyb_code       智游宝辅助码
     * @param teacher_phone  遛娃师电话
     * @param city           所在城市
     * @param goods_type     商品类型
     * @param meeting_place  集合时间
     * @param meeting_time   集合地点
     * @param message_status 短信发送情形
     * @param message_type   短信模板类型
     *
     **/
    static public function sendMessageToUser($param)
    {
        switch ($param['message_status']) {
            case self::MESSAGE_STATUS_PAY_SUCCESS         :
                $data_message_content = self::sendMessageForPaySuccess($param);
                break;
            case self::MESSAGE_STATUS_USE_SUCCESS         :
                $data_message_content = self::sendMessageForUseSuccess($param);
                break;
            case self::MESSAGE_STATUS_USE_REMIND_REFUND   :
                $data_message_content = self::sendMessageForUseRemindRefund($param);
                break;
            case self::MESSAGE_STATUS_USE_REMIND_NOREFUND :
                $data_message_content = self::sendMessageForUseRemindNoRefund($param);
                break;
            case self::MESSAGE_STATUS_REFUND_REFUND       :
                $data_message_content = self::sendMessageForRefundRefund($param);
                break;
            case self::MESSAGE_STATUS_REFUND_NOREFUND     :
                $data_message_content = self::sendMessageForRefundNoRefund($param);
                break;
            case self::MESSAGE_STATUS_ADMIN_REFUND        :
                $data_message_content = self::sendMessageForAdminRefund($param);
                break;
            case self::MESSAGE_STATUS_REFUND_SUCCESS      :
                $data_message_content = self::sendMessageForRefundSuccess($param);
                break;
            case self::MESSAGE_STATUS_NOGROUP_REFUND      :
                $data_message_content = self::sendMessageForNoGroupRefund($param);
                break;
            default :
                return false;
                break;
        }
        if (empty($data_message_content)) {
            return false;
        } else {
            return self::Send($param['phone'], $data_message_content);
        }
    }

    // 商品支付成功
    static protected function sendMessageForPaySuccess($param)
    {
        $data_message_content = '';
        if ($param['custom_content']) {
            $param['custom_content'] .= '。';
        }

        if ($param['price'] > 0) {
            $param['price'] = sprintf('%.2f元', $param['price']);
        }
        switch ($param['message_type']) {
            case self::MESSAGE_TYPE_NORMAL   :
                $data_message_content = "亲爱的小玩家，您购买的\"{$param['goods_name']} {$param['game_name']}\"已支付成功，付款金额为{$param['price']}，验证码为{$param['code']}。{$param['custom_content']}祝您遛娃愉快！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_HOTAL    :
                $data_date_time = date('Y年m月d日', $param['use_time']);
                $data_message_content = "亲爱的小玩家，您预订的{$data_date_time}\"{$param['goods_name']} {$param['game_name']}\"已成功支付，已支付{$param['price']}。订单正在确认中，请耐心等待。{$param['custom_content']}第一时间获取订单进度请添加微信客服：" . self::getWeixinName($param['city']) . "。遇到任何问题请联系玩翻天客服4008007221。";
                break;
            case self::MESSAGE_TYPE_RESERVE  :
                $data_message_content = "亲爱的小玩家，您购买的玩翻天商品\"{$param['goods_name']} {$param['game_name']}\"已支付成功，付款金额为{$param['price']}，{$param['custom_content']}祝您遛娃愉快！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ZYB      :
                $data_message_content = "亲爱的小玩家，您购买的\"{$param['goods_name']} {$param['game_name']}\"{$param['code_count']}张已预约成功，请您凭辅助码{$param['zyb_code']}换取门票。";
                break;
            case self::MESSAGE_TYPE_GROUP    :
                $data_message_content = "亲爱的小玩家，您已支付了商品\"{$param['goods_name']}\"{$param['limit_number']}人团的团费，2小时内集齐所有小玩家，就可成功购买，如果组团不成功，所付款项会退回原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ACTIVITY :
                $data_date_time = date('Y年m月d日 H:i', $param['game_time']);
                $data_message_content = "亲爱的小玩家，您报名的{$data_date_time}\"{$param['goods_name']} {$param['game_name']}\"已支付成功，付款金额为{$param['price']}，验证码为{$param['code']}。{$param['custom_content']}祝您遛娃愉快！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            default :
                $data_message_content = "亲爱的小玩家，您购买的\"{$param['goods_name']} {$param['game_name']}\"已支付成功，付款金额为{$param['price']}，验证码为{$param['code']}，{$param['custom_content']}，祝您遛娃愉快！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
        }

        return $data_message_content;
    }

    // 兑换成功 / 验证成功 / 预约成功
    static protected function sendMessageForUseSuccess($param)
    {
        $data_message_content = '';
        switch ($param['message_type']) {
            case self::MESSAGE_TYPE_NORMAL   :
                $data_message_content = "亲爱的小玩家，您已于" . date('Y-m-d H:i:s') . "使用\"{$param['goods_name']} {$param['game_name']}\"。好不好玩，你说了算，请评价一下吧！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_HOTAL    :
                $data_date_time = date('Y年m月d日', $param['use_time']);
                $data_message_content = "亲爱的小玩家，您预订的{$data_date_time}\"{$param['goods_name']} {$param['game_name']}\"，订单已预定成功，请在酒店或景区前台出示出行人有效身份证件和预定电话号码。遇到任何问题请联系玩翻天客服4008007221。立刻添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客服服务。";
                break;
            case self::MESSAGE_TYPE_RESERVE  :
                $data_message_content = "亲爱的小玩家，您购买的\"{$param['goods_name']} {$param['game_name']}\"{$param['code_count']}张已预约成功，请您凭身份证和预约电话号码换取门票。立刻添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客服服务。";
                break;
            case self::MESSAGE_TYPE_ZYB      :
                $data_message_content = "亲爱的小玩家，您已于" . date('Y-m-d H:i:s') . "使用\"{$param['goods_name']} {$param['game_name']}\"。好不好玩，你说了算，请评价一下吧！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_GROUP    :
                $data_message_content = "亲爱的小玩家，您已于" . date('Y-m-d H:i:s') . "使用\"{$param['goods_name']} {$param['game_name']}\"。好不好玩，你说了算，请评价一下吧！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ACTIVITY :
                $data_message_content = "亲爱的小玩家，您已于" . date('Y-m-d H:i:s') . "使用\"{$param['goods_name']} {$param['game_name']}\"。好不好玩，你说了算，请评价一下吧！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_MEITUAN :
                $data_message_content = "亲爱的小玩家，您购买的\"{$param['goods_name']} {$param['game_name']}\"1张已预约成功，您的辅助码为{$param['zyb_code']}，请凭辅助码换取门票入园，如遇问题请联系玩翻天客服4008007221。立刻添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客服服务。";
                break;
            default :
                $data_message_content = "亲爱的小玩家，您已于" . date('Y-m-d H:i:s') . "使用\"{$param['goods_name']} {$param['game_name']}\"。好不好玩，你说了算，请评价一下吧！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
        }

        return $data_message_content;
    }

    // 订单提醒使用 （可退）
    static protected function sendMessageForUseRemindRefund($param)
    {
        $data_message_content = '';
        switch ($param['message_type']) {
            case self::MESSAGE_TYPE_NORMAL   :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_date_end_time = date('Y-m-d H:i', $param['end_time']);
                $data_message_content = "亲爱的小玩家，您{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"最后使用期限为{$data_date_end_time}，请尽快使用！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_HOTAL    :
                $data_message_content = "温馨提示：亲爱的小玩家，在前台办理入住（或景区兑票）时，直接报预定人姓名和电话号码，能更快查到订单哟。祝您旅途愉快！遇到任何问题请联系玩翻天客服4008007221。";
                break;
            case self::MESSAGE_TYPE_RESERVE  :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_date_end_time = date('Y-m-d H:i', $param['end_time']);
                $data_message_content = "亲爱的小玩家，您{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"最后使用期限为{$data_date_end_time}，请尽快使用！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ZYB      :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_date_end_time = date('Y-m-d H:i', $param['end_time']);
                $data_message_content = "亲爱的小玩家，您{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"最后使用期限为{$data_date_end_time}，请尽快使用！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_GROUP    :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_date_end_time = date('Y-m-d H:i', $param['end_time']);
                $data_message_content = "亲爱的小玩家，您{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"最后使用期限为{$data_date_end_time}，请尽快使用！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ACTIVITY :
                $data_date = date('Y-m-d', $param['game_time']);
                $data_time = date('H:i', $param['game_time']);
                $data_meeting_time = self::getMeetingTime($param['meeting_time']);
                if (($param['game_time'] - time()) / 3600 <= 22 && ($param['game_time'] - time()) / 3600 > 2) {
                    $data_message_content = "亲爱的小玩家，您参加的" . $data_date . "\"{$param['goods_name']}\"通知：请于{$data_meeting_time}在{$param['meeting_place']}集合签到，活动将于" . $data_time . "正式开始。联系电话{$param['teacher_phone']}。";
                } else if (($param['game_time'] - time()) / 3600 <= 2) {
                    $data_message_content = "亲爱的小玩家，您参加的" . $data_date . "\"{$param['goods_name']}\"通知：{$data_meeting_time}准时集合、提前10分钟签到，请在{$param['meeting_place']}找遛娃师集合签到，遛娃师电话{$param['teacher_phone']}。";
                }
                break;
            default :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_date_end_time = date('Y-m-d H:i', $param['end_time']);
                $data_message_content = "亲爱的小玩家，您{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"最后使用期限为{$data_date_end_time}，请尽快使用！立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
        }

        return $data_message_content;
    }

    // 订单提醒使用 （不可退）
    static protected function sendMessageForUseRemindNoRefund($param)
    {
        $data_message_content = '';
        switch ($param['message_type']) {
            case self::MESSAGE_TYPE_NORMAL   :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_date_end_time = date('Y-m-d H:i', $param['end_time']);
                $data_message_content = "亲爱的小玩家，您{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"最后使用期限为{$data_date_end_time}，过期无效且不支持退款。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_HOTAL    :
                $data_message_content = "";
                break;
            case self::MESSAGE_TYPE_RESERVE  :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_date_end_time = date('Y-m-d H:i', $param['end_time']);
                $data_message_content = "亲爱的小玩家，您{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"最后使用期限为{$data_date_end_time}，过期无效且不支持退款。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ZYB      :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_date_end_time = date('Y-m-d H:i', $param['end_time']);
                $data_message_content = "亲爱的小玩家，您{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"最后使用期限为{$data_date_end_time}，过期无效且不支持退款。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_GROUP    :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_date_end_time = date('Y-m-d H:i', $param['end_time']);
                $data_message_content = "亲爱的小玩家，您{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"最后使用期限为{$data_date_end_time}，过期无效且不支持退款。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ACTIVITY :
                $data_date = date('Y-m-d', $param['game_time']);
                $data_time = date('H:i', $param['game_time']);
                $data_meeting_time = self::getMeetingTime($param['meeting_time']);
                if (($param['game_time'] - time()) / 3600 <= 22 && ($param['game_time'] - time()) / 3600 > 2) {
                    $data_message_content = "亲爱的小玩家，您参加的" . $data_date . "\"{$param['goods_name']}\"通知：请于{$data_meeting_time}在{$param['meeting_place']}集合签到，活动将于" . $data_time . "正式开始。联系电话{$param['teacher_phone']}。";
                } else if (($param['game_time'] - time()) / 3600 <= 2) {
                    $data_message_content = "亲爱的小玩家，您参加的" . $data_date . "\"{$param['goods_name']}\"通知：{$data_meeting_time}准时发车、提前10分钟签到，请在{$param['meeting_place']}找遛娃师集合签到，遛娃师电话{$param['teacher_phone']}。";
                }
                break;
            default :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_date_end_time = date('Y-m-d H:i', $param['end_time']);
                $data_message_content = "亲爱的小玩家，您{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"最后使用期限为{$data_date_end_time}，过期无效且不支持退款。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
        }

        return $data_message_content;
    }

    // （用户发起）退款时提醒（可退）
    static protected function sendMessageForRefundRefund($param)
    {
        $data_message_content = '';
        switch ($param['message_type']) {
            case self::MESSAGE_TYPE_NORMAL   :
                $data_message_content = "亲爱的小玩家，您购买\"{$param['goods_name']} {$param['game_name']}\"退订申请已收到，退款审核后，款项将于3-5个工作日内退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_HOTAL    :
                $data_message_content = "亲爱的小玩家，您购买\"{$param['goods_name']} {$param['game_name']}\"退订申请已收到，退款审核后，款项将于3-5个工作日内退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_RESERVE  :
                $data_message_content = "亲爱的小玩家，您购买\"{$param['goods_name']} {$param['game_name']}\"退订申请已收到，退款审核后，款项将于3-5个工作日内退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ZYB      :
                $data_message_content = "亲爱的小玩家，您购买\"{$param['goods_name']} {$param['game_name']}\"退订申请已收到，退款审核后，款项将于3-5个工作日内退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_GROUP    :
                $data_message_content = "亲爱的小玩家，您购买\"{$param['goods_name']} {$param['game_name']}\"退订申请已收到，退款审核后，款项将于3-5个工作日内退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ACTIVITY :
                $data_date_time = date('Y年m月d日 H:i', $param['game_time']);
                $data_message_content = "亲爱的小玩家，您已取消参加{$data_date_time}\"{$param['goods_name']} {$param['game_name']}\"，退款审核后，款项将于3-5个工作日内退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            default :
                $data_message_content = "亲爱的小玩家，您购买\"{$param['goods_name']} {$param['game_name']}\"退订申请已收到，退款审核后，款项将于3-5个工作日内退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
        }

        return $data_message_content;
    }

    // （用户发起）退款时提醒（不可退）
    static protected function sendMessageForRefundNoRefund($param)
    {
        $data_message_content = '';
        switch ($param['message_type']) {
            case self::MESSAGE_TYPE_NORMAL   :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_message_content = "亲爱的小玩家，您于{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"已过期无效，特价商品恕不接受退款申请哦。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_HOTAL    :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_message_content = "亲爱的小玩家，您于{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"已过期无效，特价商品恕不接受退款申请哦。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_RESERVE  :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_message_content = "亲爱的小玩家，您于{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"已过期无效，特价商品恕不接受退款申请哦。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ZYB      :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_message_content = "亲爱的小玩家，您于{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"已过期无效，特价商品恕不接受退款申请哦。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_GROUP    :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_message_content = "亲爱的小玩家，您于{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"已过期无效，特价商品恕不接受退款申请哦。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ACTIVITY :
                $data_message_content = "";
                break;
            default :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_message_content = "亲爱的小玩家，您于{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"已过期无效，特价商品恕不接受退款申请哦。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
        }

        return $data_message_content;
    }

    // （后台发起）退款时提醒
    static protected function sendMessageForAdminRefund($param)
    {
        $data_message_content = '';
        switch ($param['message_type']) {
            case self::MESSAGE_TYPE_NORMAL   :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_message_content = "亲爱的小玩家，您于{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"因主办方原因、游玩活动取消而发起退款，款项将于3-5个工作日内退回至原支付账户。建议您尝试参加玩翻天的其他游玩活动，带来不便十分抱歉。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_HOTAL    :
                $data_date_time = date('Y年m月d日', $param['use_time']);
                $data_message_content = "亲爱的小玩家，您预定于{$data_date_time}入住的\"{$param['goods_name']} {$param['game_name']}\"因酒店该房型满房而预约不到房间啦，我们已为本条订单进行退款，款项将于3-5个工作日内退回至原支付账户。建议您尝试在玩翻天预约别的酒店或改期，带来不便十分抱歉。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_RESERVE  :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_message_content = "亲爱的小玩家，您于{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"因主办方原因、游玩活动取消而发起退款，款项将于3-5个工作日内退回至原支付账户。建议您尝试参加玩翻天的其他游玩活动，带来不便十分抱歉。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ZYB      :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_message_content = "亲爱的小玩家，您于{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"因主办方原因、游玩活动取消而发起退款，款项将于3-5个工作日内退回至原支付账户。建议您尝试参加玩翻天的其他游玩活动，带来不便十分抱歉。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_GROUP    :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_message_content = "亲爱的小玩家，您于{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"因主办方原因、游玩活动取消而发起退款，款项将于3-5个工作日内退回至原支付账户。建议您尝试参加玩翻天的其他游玩活动，带来不便十分抱歉。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ACTIVITY :
                $data_date_time = date('Y年m月d日 H:i', $param['game_time']);
                $data_message_content = "亲爱的小玩家，您购买的{$data_date_time}\"{$param['goods_name']} {$param['game_name']}\"因主办方原因、活动场次取消而发起退款，款项将于3-5个工作日内退回至原支付账户。建议您尝试参加玩翻天的其他遛娃活动，带来不便十分抱歉。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            default :
                $data_date_buy_time = date('Y-m-d H:i:s', $param['buy_time']);
                $data_message_content = "亲爱的小玩家，您于{$data_date_buy_time}购买的\"{$param['goods_name']} {$param['game_name']}\"因主办方原因、游玩活动取消而发起退款，款项将于3-5个工作日内退回至原支付账户。建议您尝试参加玩翻天的其他游玩活动，带来不便十分抱歉。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
        }

        return $data_message_content;
    }

    // 退款至支付账户
    static protected function sendMessageForRefundSuccess($param)
    {
        $data_message_content = '';
        switch ($param['message_type']) {
            case self::MESSAGE_TYPE_NORMAL   :
                $data_message_content = "亲爱的小玩家，您购买的\"{$param['goods_name']} {$param['game_name']}\"已经退订成功，款项已退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_HOTAL    :
                $data_message_content = "亲爱的小玩家，您购买的\"{$param['goods_name']} {$param['game_name']}\"已经退订成功，款项已退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_RESERVE  :
                $data_message_content = "亲爱的小玩家，您购买的\"{$param['goods_name']} {$param['game_name']}\"已经退订成功，款项已退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ZYB      :
                $data_message_content = "亲爱的小玩家，您购买的\"{$param['goods_name']} {$param['game_name']}\"已经退订成功，款项已退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_GROUP    :
                $data_message_content = "亲爱的小玩家，您购买的\"{$param['goods_name']} {$param['game_name']}\"已经退订成功，款项已退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ACTIVITY :
                $data_date_time = date('Y年m月d日 H:i', $param['game_time']);
                $data_message_content = "亲爱的小玩家，您购买的{$data_date_time}\"{$param['goods_name']} {$param['game_name']}\"已经退订成功，款项已退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            default :
                $data_message_content = "亲爱的小玩家，您购买的\"{$param['goods_name']} {$param['game_name']}\"已经退订成功，款项已退回至原支付账户。立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
        }
        return $data_message_content;
    }

    // 未成团退款
    static protected function sendMessageForNoGroupRefund($param)
    {
        $data_message_content = '';
        switch ($param['message_type']) {
            case self::MESSAGE_TYPE_NORMAL   :
                $data_message_content = "亲爱的小玩家，您参加的\"{$param['goods_name']}\"由于在规定的时间内未满员,现已进入自动退款流程,详情请致电400-800-7221,立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_HOTAL    :
                $data_message_content = "亲爱的小玩家，您参加的\"{$param['goods_name']}\"由于在规定的时间内未满员,现已进入自动退款流程,详情请致电400-800-7221,立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_RESERVE  :
                $data_message_content = "亲爱的小玩家，您参加的\"{$param['goods_name']}\"由于在规定的时间内未满员,现已进入自动退款流程,详情请致电400-800-7221,立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ZYB      :
                $data_message_content = "亲爱的小玩家，您参加的\"{$param['goods_name']}\"由于在规定的时间内未满员,现已进入自动退款流程,详情请致电400-800-7221,立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_GROUP    :
                $data_message_content = "亲爱的小玩家，您参加的\"{$param['goods_name']}\"由于在规定的时间内未满员,现已进入自动退款流程,详情请致电400-800-7221,立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            case self::MESSAGE_TYPE_ACTIVITY :
                $data_message_content = "亲爱的小玩家，您参加的\"{$param['goods_name']}\"由于在规定的时间内未满员,现已进入自动退款流程,详情请致电400-800-7221,立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
            default :
                $data_message_content = "亲爱的小玩家，您参加的\"{$param['goods_name']}\"由于在规定的时间内未满员,现已进入自动退款流程,详情请致电400-800-7221,立即添加微信号：" . self::getWeixinName($param['city']) . "，即可免费获取私人专享客户服务。";
                break;
        }
        return $data_message_content;
    }

    // 将日期转化为含有上午、下午、晚上、早上等文字表述的时间
    static protected function getMeetingTime($meeting_time)
    {
        $data_str_time = '';
        if (date("H", $meeting_time) > 6 && date("H", $meeting_time) < 9) {
            $data_str_time = '早上' . date('H:i', $meeting_time);
        } else if (date("H", $meeting_time) > 9 && date("H", $meeting_time) < 12) {
            $data_str_time = '上午' . date('H:i', $meeting_time);
        } else if (date("H", $meeting_time) > 12 && date("H", $meeting_time) < 18) {
            $data_str_time = '下午' . date('H:i', $meeting_time);
        } else if (date("H", $meeting_time) > 18 || date("H", $meeting_time) < 6) {
            $data_str_time = '晚上' . date('H:i', $meeting_time);
        }
        return $data_str_time;
    }

    //通用 单个商品退订成功
    public static function Send1($phone, $coupon_name, $city = 'WH')
    {
        $content = "“玩翻天”亲爱的小玩家，您购买的\"{$coupon_name}\"已退订成功，款项已退还至玩翻天账户余额。立即添加微信号：" . self::getWeixinName($city) . "，即可免费获取私人专享客户服务。";
        return self::Send($phone, $content);
    }

    //通用 多个商品退订成功   暂未使用
//    public static function Send2($phone)
//    {
//        $content = "“玩翻天”亲爱的小玩家，您购买的{商品名称}、{商品名称}……已退订成功，款项已退还至玩翻天账户余额。立即添加微信号：".self::getWeixinName($city)."，即可免费获取私人专享客户服务。";
//        return self::Send($phone, $content);
//    }

    //通用 兑换成功
    public static function Send3($phone, $coupon_name, $city = 'WH')
    {
        $time = date('Y-m-d H:i');
        $content = "“玩翻天”亲爱的小玩家，您已于{$time}使用\"{$coupon_name}\"。好不好玩，你说了算，请评价一下吧！立即添加微信号：" . self::getWeixinName($city) . "，即可免费获取私人专享客户服务。";
        return self::Send($phone, $content);
    }

    //通用 付款成功
    public static function Send4($phone, $coupon_name, $pay_money, $code_len, $city = 'WH')
    {
        $content = "“玩翻天”亲爱的小玩家，您购买的玩翻天商品\"{$coupon_name}\"已支付成功，付款金额为{$pay_money}元，验证码为{$code_len}，祝您遛娃愉快！立即添加微信号：" . self::getWeixinName($city) . "，即可免费获取私人专享客户服务。";
        return self::Send($phone, $content);
    }

    //通用 手机验证码
    public static function Send5($phone, $city = 'WH')
    {
        $code = self::make_four_code();
        $content = "“玩翻天”{$code}您的验证码，5分钟内有效，如非本人操作，请忽略。立即添加微信号：" . self::getWeixinName($city) . "，即可免费获取私人专享客户服务。";
        $status = self::Send($phone, $content);
        return $status ? $code : false;
    }

    //通用 可退订单退款前提醒使用
    public static function Send6($phone, $coupon_name, $pay_time, $use_end_time, $city = 'WH')
    {

        $content = "“玩翻天”亲爱的小玩家，您" . date('Y-m-d H:i', $pay_time) . "购买的\"{$coupon_name}\"最后使用期限为" . date('Y-m-d H:i', $use_end_time) . "，请尽快使用！立即添加微信号：" . self::getWeixinName($city) . "，即可免费获取私人专享客户服务。";
        return self::Send($phone, $content);
    }

    //通用 不可退订单退款前提醒使用
    public static function Send7($phone, $coupon_name, $pay_time, $use_end_time, $city = 'WH')
    {
        $content = "“玩翻天”亲爱的小玩家，您" . date('Y-m-d H:i', $pay_time) . "购买的\"{$coupon_name}\"最后使用期限为" . date('Y-m-d H:i', $use_end_time) . "，过期无效且不支持退款。立即添加微信号：" . self::getWeixinName($city) . "，即可免费获取私人专享客户服务。";
        return self::Send($phone, $content);
    }

    //通用 不可退订退款提醒
    public static function Send8($phone, $coupon_name, $pay_time, $city = 'WH')
    {
        $content = "“玩翻天”亲爱的小玩家，您于" . date('Y-m-d H:i', $pay_time) . "购买的\"{$coupon_name}\"已过期无效，特价商品恕不接受退款申请哦。立即添加微信号：" . self::getWeixinName($city) . "，即可免费获取私人专享客户服务。";
        return self::Send($phone, $content);
    }


    //活动 退款时提醒（可退款）
    public static function Send9($phone, $coupon_name, $city = 'WH')
    {
        //$content = "“玩翻天”亲爱的小玩家，您已取消参加\"{$coupon_name}\"，款项已退回至玩翻天账户，在退款订单详情页面可申请原路退回。立即添加微信号：".self::getWeixinName($city)."，即可免费获取私人专享客户服务。";

        $content = "“玩翻天”亲爱的小玩家，您已取消参加\"{$coupon_name}\"，款项已退回至支付账户。立即添加微信号：" . self::getWeixinName($city) . "，即可免费获取私人专享客户服务。";
        return self::Send($phone, $content);
    }

    //活动 退款时提醒（不可退款）
    public static function Send10($phone, $coupon_name, $city = 'WH')
    {
        $content = "“玩翻天”亲爱的小玩家，您已取消参加\"{$coupon_name}\"，特价商品，恕不接受退款申请哦。立即添加微信号：" . self::getWeixinName($city) . "，即可免费获取私人专享客户服务。";
        return self::Send($phone, $content);
    }


    /**
     *
     * 活动 活动开始前22个小时
     * @param $phone
     * @param $coupon_name | 活动名称
     * @param $join_time |集合时间
     * @param $join_address |集合地点
     * @param $start_time |开始时间
     * @param $leader_phone |遛娃师电话
     * @return string
     */
    public static function Send11($phone, $coupon_name, $join_time, $join_address, $start_time, $leader_phone)
    {
        $content = "亲爱的小玩家，您参加的" . date('Y-m-d H:i', $start_time) . "\"{$coupon_name}\"通知：请于" . date('Y-m-d H:i', $join_time) . "在{$join_address}集合签到，活动将于" . date('Y-m-d H:i', $start_time) . "正式开始。联系电话{$leader_phone}。";
        return self::Send($phone, $content);
    }


    /**
     * 投保短信
     * @param $phone
     * @param $ProductName |保单名称
     * @param $Insureds |投保人数
     * @param $PolicyNo |保单号
     * @return string
     */
    public static function Send12($phone, $ProductName, $Insureds, $PolicyNo)
    {
        $content = "您投保的{$ProductName}，投保人数{$Insureds}人，保单号：{$PolicyNo}，已成功出单，详情请致电400-800-7221.保险公司报案电话 400-999-5507.";
        return self::Send($phone, $content);
    }


    //预约成功
    public static function Send13($phone, $coupon_name, $code_number, $zyb_code)
    {
        $content = "“玩翻天”亲爱的小玩家，您购买的 \"{$coupon_name}\"{$code_number}张已预约成功，请您凭辅助码{$zyb_code}换取门票";
        return self::Send($phone, $content);
    }


    //团主支付成功短信
    public static function Send15($phone, $coupon_name, $limit_number)
    {
        $content = "“玩翻天”亲爱的小玩家，你已支付了商品\"{$coupon_name}\"{$limit_number}人团的团费，2小时内集齐所有小玩家，就可成功购买，如果组团不成功，已经支付的钱会直接返回你的玩翻天余额账户。";
        return self::Send($phone, $content);
    }

    //商家 验证成功
    public static function Send16($phone, $coupon_name, $id, $password)
    {
        $time = date('Y-m-d H:i:s');
        $content = "{$id}{$password}验证成功，验证商品\"{$coupon_name}\",验证时间{$time}";
        return self::Send($phone, $content);
    }


    //活动解散短信
    public static function Send17($phone, $coupon_name, $city = 'WH')
    {
        $content = "亲爱的小玩家，您参加的\"{$coupon_name}\"由于在规定的时间内未满员,现已进入自动退款流程,详情请致电400-800-7221,立即添加微信号：" . self::getWeixinName($city) . "，即可免费获取私人专享客户服务。";
        return self::Send($phone, $content);
    }


    //商品提交退款短信 活动提交退款
    public static function Send18($phone, $code, $coupon_name, $city = 'WH')
    {
        $content = "亲爱的小玩家，您购买的\"{$coupon_name}\", 兑换码为\"{$code}\"的退订申请小玩已收到，我们将及时为您办理。立即添加微信号：" . self::getWeixinName($city) . "，即可免费获取私人专享客户服务。";
        return self::Send($phone, $content);
    }

    //商品受理退款时间
    public static function Send19($phone, $code, $coupon_name, $city = 'WH')
    {

        $content = "亲爱的小玩家，您购买的\"{$coupon_name}\", 兑换码为\"{$code}\"的退订业务已受理成功，款项将于3-5个工作日内返回至您的原支付账户，请注意查收。立即添加微信号：" . self::getWeixinName($city) . "，即可免费获取私人专享客户服务。";
        return self::Send($phone, $content);
    }

    // 酒店类商品付款成功
    public static function Send20($phone, $coupon_name, $price_name, $pay_price, $use_time, $city = 'WH')
    {
        $content = "支付成功：亲爱的小玩家，您预订的{$use_time}入住的{$coupon_name}{$price_name}已成功支付，实际支付{$pay_price}元。房间正在确认中，请耐心等待。第一时间获取订房进度请添加微信客服：" . self::getWeixinName($city) . "。遇到任何问题请联系玩翻天客服4008007221。";
        return self::Send($phone, $content);
    }

    // 酒店类商品预约成功
    public static function Send21($phone, $coupon_name, $price_name, $use_time, $city = 'WH')
    {
        $content = "预订成功：亲爱的小玩家，您预订于{$use_time}入住的{$coupon_name}{$price_name}，房间已预定成功，入住请在酒店前台出示入住人有效身份证件和预定电话号码。遇到任何问题请联系玩翻天客服4008007221。立刻添加微信号：" . self::getWeixinName($city) . "，即可免费获取私人专享客服服务。";
        return self::Send($phone, $content);
    }

    // 酒店类商品游玩日期当天提醒
    public static function Send22($phone)
    {
        $content = "温馨提示：亲爱的小玩家，在前台办理入住时，直接报预定人姓名和电话号码，能更快查到订单哟。祝您旅途愉快！遇到任何问题请联系玩翻天客服4008007221。";
        return self::Send($phone, $content);
    }

    //发送短信通用方法
    public static function Send($phone, $content)
    {

        $last_send = md5($phone . $content);
        $ttl = RedCache::get($last_send);
        if($ttl){
            //相同内容的短信5分钟内只发送一次
            return true;
        }else{
            RedCache::set($last_send,time(),60*5);
        }

        //测试环境只有指定的用户 可以收到短信
        $pass_list = ServiceManager::getConfig('user_pass_list');
        if (!Common::isUp()) {
            if (!in_array($phone, $pass_list)) {
                return true;
            }
        }

        $client = new \swoole_client(SWOOLE_SOCK_TCP);
        if (!$client->connect('127.0.0.1', 9501, 1)) {
//            echo "connect failed. Error: {$client->errCode}\n";
            return '短信服务异常';
        } else {
            $client->send(json_encode(array('action' => 'phoneMessage', 'params' => array('phone' => $phone, 'content' => $content))));
            $msg = $client->recv();
            $client->close();
            return $msg;
        }

//        $pid = pcntl_fork();
//        //父进程和子进程都会执行下面代码
//        if ($pid == -1) {
//            //错误处理：创建子进程失败时返回-1.
//            return self::Send_Message($phone, $content);
//        } else if ($pid) {
//            //父进程会得到子进程号，所以这里是父进程执行的逻辑
//            // pcntl_wait($status); //等待子进程中断，防止子进程成为僵尸进程。
//            return true;
//        } else {
//            //子进程得到的$pid为0, 所以这里是子进程执行的逻辑。
//            self::Send_Message($phone, $content);
//            exit;
//        }

    }

    /**
     * @param $phone
     * @return bool|int
     * 发送5位数验证码,如果成功返回发送的验证码
     */
    public static function SendAuthCode($phone)
    {
        $code = self::make_code();
        $msg = "“玩翻天”{$code}您的验证码，5分钟内有效，如非本人操作，请忽略";
        $status = self::Send($phone, $msg);
        return $status ? $code : false;
    }

    /**
     * @return int
     * 返回验证码 数字 五位
     */
    public static function make_code()
    {
        return rand(10000, 99999);
    }

    /**
     * @return int
     * 返回验证码 数字 4位
     */
    public static function make_four_code()
    {
        return rand(1000, 9999);
    }

    // 发送消息
    public function sendMes($uid, $type, $title, $message, $link_id)
    {
        $data_time = time();
        $this->_getPlayUserMessageTable()->insert(array('uid' => $uid, 'type' => $type, 'title' => $title, 'deadline' => $data_time, 'message' => $message, 'link_id' => json_encode($link_id)));

        switch ($type) {
            case 9 :
                $data_lid = (int)$link_id['lid'];
                break;
            case 10:
                $data_lid = (int)$link_id['lid'];
                break;
            case 11:
                $data_lid = (int)$link_id['lid'];
                break;
            case 12:
                $data_lid = (int)$uid;
                break;
            case 13:
                $data_lid = (int)$link_id['id'];
                break;
            case 14:
                $data_lid = (int)$link_id['id'];
                break;
            case 15:
                $data_lid = (int)$link_id['mid'];
                break;
            case 16:
                $data_lid = (int)$link_id['reply'];
                break;
        }
        $pdo = $this->_getAdapter();
        $pdo->query(" INSERT INTO play_message_push (type, lid, deadline) value ('{$type}', {$data_lid}, {$data_time})", array())->count();
        return true;
    }

    // 推送消息
    public function sendInform($user_id, $user_token, $title, $inform, $inform_info, $type, $id)
    {
        $data_send = array(
            'title' => $inform,
            'info' => $inform_info,
            'type' => $type,
            'id' => $id ? $id : 0,
            'time' => time(),
        );

        $geTui = new GeTui();

        $data_token = substr($user_token, 0, 10);
        $data_send_status = $geTui->Push($user_id . '__' . $data_token, $title, json_encode($data_send));
        return $data_send_status;
    }
}
