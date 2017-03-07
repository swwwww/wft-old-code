<?php

namespace Deyi;


class Request
{

    public function __construct()
    {

    }

    public static function post($url, $post_data = array(), $timeout = 20)
    {

        if (is_array($post_data)) {
            $post_data = http_build_query($post_data);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false); //设定是否输出页面内容
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout); //连接超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); //下载超时
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); //强制使用IPV4协议解析域名
        curl_setopt($ch, CURLOPT_POST, count($post_data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
//        $rinfo=curl_getinfo($ch);  //请求信息
        curl_close($ch);
        return $output;


//        if (is_array($post_data)) {
//            $post_data = http_build_query($post_data);
//        }
//        ini_set('default_socket_timeout',$timeout);
//        $opts = array('http' =>
//            array(
//                'method' => "POST",
//                'header' => 'Content-type: application/x-www-form-urlencoded',
//                'content' => $post_data,
//                'timeout' => $timeout
//            )
//        );
//        $context = stream_context_create($opts);
//        return file_get_contents($url, false, $context);
    }

    public static function get($url, $timeout = 20)
    {

        $ch = curl_init();
        //设置选项，包括URL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0); ////设定是否输出页面内容
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout); //连接超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); //下载超时
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); //强制使用IPV4协议解析域名
        $output = curl_exec($ch);
//        $rinfo=curl_getinfo($ch);  //请求信息
        curl_close($ch);
        return $output;

        /************ 其他方式 ***************/
//        ini_set('default_socket_timeout',$timeout);
//        $opts = array('http' =>
//            array(
//                'method' => 'GET',
//                'header' => 'Content-type: application/x-www-form-urlencoded',
//                'timeout' => $timeout
//            )
//        );
//        $context = stream_context_create($opts);
//        return file_get_contents($url, false, $context);

    }

}