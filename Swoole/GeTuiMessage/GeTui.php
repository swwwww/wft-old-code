<?php

require_once(__DIR__.'/../../vendor/Deyi/GeTui/IGt.Push.php');
require_once(__DIR__.'/../../vendor/Deyi/GeTui/igetui/IGt.AppMessage.php');
require_once(__DIR__.'/../../vendor/Deyi/GeTui/igetui/IGt.APNPayload.php');
require_once(__DIR__.'/../../vendor/Deyi/GeTui/igetui/template/IGt.BaseTemplate.php');
require_once(__DIR__.'/../../vendor/Deyi/GeTui/IGt.Batch.php');
require_once(__DIR__.'/../../vendor/Deyi/getServerConfig.php');


define('APPKEY', \Deyi\getServerConfig::get('getui_account')['APPKEY']);
define('APPID', \Deyi\getServerConfig::get('getui_account')['APPID']);
define('MASTERSECRET', \Deyi\getServerConfig::get('getui_account')['MASTERSECRET']);
define('HOST', \Deyi\getServerConfig::get('getui_account')['HOST']);

class GeTui
{
    //单个用户推送
    /**
     * @param $title 推送title
     * @param $cid   别名（客户端设置的别名）
     * @param $content
     * @return Array
     */
    public function pushMessageToSingle($cid, $title, $content)
    {

        $igt = new IGeTui(HOST, APPKEY, MASTERSECRET);
        $template = $this->IGtTransmissionTemplate($title, $content);

        //个推信息体
        $message = new IGtSingleMessage();

        $message->set_isOffline(true);//是否离线
        $message->set_offlineExpireTime(43200000);//离线时间
        $message->set_data($template);//设置推送消息类型
//      $message->set_PushNetWorkType(0);//设置是否根据WIFI推送消息，1为wifi推送，0为不限制推送

        //接收方
        $target = new IGtTarget();
        $target->set_appId(APPID);

        //根据传的是clientId 还是 别名
//      $target->set_clientId($cid);
        $target->set_alias($cid);

        $rep = $igt->pushMessageToSingle($message, $target);

        return $rep;


        //推送成功与否
        /*try {
            $rep = $igt->pushMessageToSingle($message, $target);
            var_dump($rep);
            echo ("<br><br>");

        }catch(RequestException $e){
            $requstId =e.getRequestId();
            $rep = $igt->pushMessageToSingle($message, $target,$requstId);
            var_dump($rep);
            echo ("<br><br>");
        }*/
    }

    //多个用户 暂时未用到
    /**
     * @param $content
     * @param $alias  用户列表 (别名)
     * @param $task   推送标识
     * @throws \Exception
     */
    function pushMessageToList($content, $alias, $task)
    {
        putenv("gexin_pushList_needDetails=true");
        putenv("gexin_pushList_needAsync=true");

        $igt = new IGeTui(HOST, APPKEY, MASTERSECRET);
        //消息模版：
        // 1.TransmissionTemplate:透传功能模板
        // 2.LinkTemplate:通知打开链接功能模板
        // 3.NotificationTemplate：通知透传功能模板
        // 4.NotyPopLoadTemplate：通知弹框下载功能模板


        //$template = IGtNotyPopLoadTemplateDemo();
        //$template = IGtLinkTemplateDemo();
        $template = $this->IGtTransmissionTemplate($content);
        //$template = IGtTransmissionTemplateDemo();
        //个推信息体
        $message = new IGtListMessage();

        $message->set_isOffline(true);//是否离线
        $message->set_offlineExpireTime(3600 * 12 * 1000);//离线时间
        $message->set_data($template);//设置推送消息类型
//     $message->set_PushNetWorkType(1);	//设置是否根据WIFI推送消息，1为wifi推送，0为不限制推送
//    $contentId = $igt->getContentId($message);
        $contentId = $igt->getContentId($message, $task);    //根据TaskId设置组名，支持下划线，中文，英文，数字

        //接收方1

        $target1 = new IGtTarget();
        foreach ($alias as $v) {
            $target1->set_appId(APPID);
            //      $target1->set_clientId(CID);
            $target1->set_alias($v);
            $targetList[] = $target1;
        }


        $rep = $igt->pushMessageToList($contentId, $targetList);
        //var_dump($rep);
        // echo("<br><br>");

        return $rep;
    }


    //群推接口
    /**
     * @param $title
     * @param $content
     * @param $task
     * @param $duration //持续时间
     * @param $city //城市编号
     * @return mixed|null
     */
    function pushMessageToApp($title, $content, $task, $duration = 43200000, $city)
    {
        $igt = new IGeTui(HOST, APPKEY, MASTERSECRET);

        //消息模板

        $template = $this->IGtTransmissionTemplate($title, $content);
        //个推信息体
        //基于应用消息体
        $message = new IGtAppMessage();

        $message->set_isOffline(true);
        $message->set_offlineExpireTime($duration);//离线时间单位为毫秒，例，两个小时离线为3600*1000*2
        $message->set_data($template);
//	    $message->set_PushNetWorkType(1);	//设置是否根据WIFI推送消息，1为wifi推送，0为不限制推送
//      $message->set_speed(50);          //控速推送，设置每秒消息的下发量

        $message->set_appIdList(array(APPID));
        //$message->set_phoneTypeList(array('ANDROID'));
        //$message->set_provinceList(array('浙江','北京','河南'));

        if ($city) {
            $message->set_provinceList(array($city)); //单独城市
        }

        //http://docs.getui.com/pages/viewpage.action?pageId=1213564 个推文档

        $rep = $igt->pushMessageToApp($message, $task);//根据TaskId设置组名，支持下划线，中文，英文，数字
        return $rep;
    }


    //模板2 透传模板
    function IGtTransmissionTemplate($title, $content)
    {
        $template = new IGtTransmissionTemplate();
        $template->set_appId(APPID);//应用appid
        $template->set_appkey(APPKEY);//应用appkey
        $template->set_transmissionType(2);//透传消息类型
        $template->set_transmissionContent($content);//透传内容
        //$template->set_duration(BEGINTIME,ENDTIME); //设置ANDROID客户端在此时间区间内展示消息
        //APN简单推送
//        $template = new IGtAPNTemplate();
//        $apn = new IGtAPNPayload();
//        $alertmsg=new SimpleAlertMsg();
//        $alertmsg->alertMsg="";
//        $apn->alertMsg=$alertmsg;
////        $apn->badge=2;
////        $apn->sound="";
//        $apn->add_customMsg("payload","payload");
//        $apn->contentAvailable=1;
//        $apn->category="ACTIONABLE";
//        $template->set_apnInfo($apn);
//        $message = new IGtSingleMessage();

        //APN高级推送
        $apn = new IGtAPNPayload();
        $alertmsg = new DictionaryAlertMsg();
        $alertmsg->body = $title;
        //$alertmsg->actionLocKey="ActionLockey";
        $alertmsg->locKey = $title;
        //$alertmsg->locArgs=array("locargs");
        //$alertmsg->launchImage="launchimage";
//        IOS8.2 支持
        //$alertmsg->title="Title";
        // $alertmsg->titleLocKey="TitleLocKey";
        //$alertmsg->titleLocArgs=array("TitleLocArg");

        $apn->alertMsg = $alertmsg;
        $apn->badge = 1;
        $apn->sound = "";
        $apn->add_customMsg("payload", json_decode($content));
        //$apn->contentAvailable=0;
        //$apn->category="ACTIONABLE";
        $template->set_apnInfo($apn);

        //PushApn老方式传参
//      $template = new IGtAPNTemplate();
//      $template->set_pushInfo("", 10, "", "com.gexin.ios.silence", "", "", "", "");

        return $template;
    }

    //推送任务停止
    /**
     * @param $task_id
     * @return bool
     */
    function stoptask($task_id)
    {

        $igt = new IGeTui(HOST, APPKEY, MASTERSECRET);
        return $igt->stop($task_id);
    }

}



