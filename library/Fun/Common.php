<?php
namespace library\Fun;

use library\Service\ServiceManager;

/**
 * Created by IntelliJ IDEA.
 * User: wwjie
 * Date: 2016/9/22
 * Time: 16:33
 */
class Common
{
    static public function toUTF8($str)
    {
        return mb_convert_encoding($str, 'UTF-8', 'GBK'); //编码转换为utf-8
    }

    static public function toGBK($str)
    {
        return mb_convert_encoding($str, 'GBK', 'UTF-8'); //编码转换为gbk
    }


    static public function isWindows()
    {
        if (PATH_SEPARATOR == ':') {
            //linux
            return false;
        } else {
            return true;
        }
    }

    static public function isUp()
    {
        if (ServiceManager::getConfig('url') === 'https://wan.wanfantian.com') {
            return true;
        } else {
            return false;
        }
    }

    //获取客户端信息  ios是自定义的useragent
    static public function getClientinfo()
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

    static public function getUrlPath($url = '')
    {
        if (!$url) {
            $url = $_SERVER['REQUEST_URI'];
        }
        $tp = strpos($url, '?');
        if ($tp === false) {
            return $url;
        } else {
            $strlen = strlen($url);
            return substr($url, -$strlen, $tp);
        }
    }

}