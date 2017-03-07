<?php
namespace Deyi;


class Push
{
    private $apikey = '4AVFb3mEUN3an8WKZwt1aPYF';
    private $secretKey = 'q8XhnTqH0l3cWWF1HWh0QBEvBuFLDbSu';
	private $apikey2 = 'vop5UrPlzxIQhyTAkQqflmQS';
	private $secretKey2 = 'yuw1UV2PasziHbZXjopbxyBVUo73LsYg';
    private $isoKey = 'y1D9e8E4lYeIi';

    public function pushAndroid($title, $description, $user_id, $device_id, $type, $valueArray, $push_type = 1)
    {

        //android 推送
        $message = array('title' => $title, 'description' => $description, 'custom_content' => array('action' => $type, 'value' => $valueArray));
        $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        $time = time();
        $http = 'POST';
        $baiduurl = 'https://channel.api.duapp.com/rest/2.0/channel/channel';
	    if($push_type == 3) {
		    $action = array(
			    'apikey' => $this->apikey,
			    'method' => 'push_msg',
			    'push_type' => $push_type, // 3就是全站
			    'messages' => $message,
			    'msg_keys' => time(),
			    'timestamp' => $time,
		    );
	    } else {
		    $action = array(
			    'apikey' => $this->apikey,
			    'method' => 'push_msg',
			    'push_type' => $push_type, // 3就是全站
			    'user_id' => $user_id,
                'channel_id'=>$device_id,
			    'messages' => $message,
			    'msg_keys' => time(),
			    'timestamp' => $time,
		    );
	    }

        ksort($action);
        $sign = MD5(urlencode($http . $baiduurl . str_replace('&', '', urldecode(http_build_query($action))) . $this->secretKey));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, urldecode(http_build_query($action)) . '&sign=' . $sign);
        curl_setopt($ch, CURLOPT_URL, $baiduurl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $da = curl_exec($ch);
        curl_close($ch);
        $info = json_decode($da, 1);
        return $info['response_params']['success_amount'];

    }

	public function pushIos2($title, $description, $user_id, $device_id, $type, $valueArray, $push_type = 3)
	{

        //https://channel.iospush.api.duapp.com
		//IOS全站 推送
		$message = array('title' => $title, 'description' => $description, 'custom_content' => array('type' => $type, 'value' => $valueArray));
		$message = json_encode($message, JSON_UNESCAPED_UNICODE);
		$time = time();
		$http = 'POST';
		$baiduurl = 'https://channel.api.duapp.com/rest/2.0/channel/channel';

		$action = array(
			'apikey' => $this->apikey,
			'method' => 'push_msg',
			'push_type' => 3, // 3就是全站
//			'user_id' => $user_id,
			'messages' => $message,
			'msg_keys' => time(),
			'timestamp' => $time,
			'device_type' => 4, // 4 代表IOS设备
			'message_type' => 1
		);
		ksort($action);
		$sign = MD5(urlencode($http . $baiduurl . str_replace('&', '', urldecode(http_build_query($action))) . $this->secretKey));
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, urldecode(http_build_query($action)) . '&sign=' . $sign);
		curl_setopt($ch, CURLOPT_URL, $baiduurl);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

		$da = curl_exec($ch);
		curl_close($ch);
		$info = json_decode($da, 1);
        return $info['response_params']['success_amount'];


    }

//IOS推送
    public function pushIos($title, $device_id, $type, $valueArray)
    {
        $passphrase = $this->isoKey;
        $pem = dirname(__FILE__) . '/aps_production_key.pem'; //证书
//        $pem = dirname(__FILE__) . '/aps_production_key_down.pem'; //证书
	  //  echo file_get_contents($pem);
        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', $pem);
        stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);
        //[[UIApplicationsharedApplication] setApplicationIconBadgeNumber:456];
        // Open a connection to the APNS server
        //这个为正是的发布地址
        $fp = stream_socket_client('ssl://gateway.push.apple.com:2195', $err, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        //这个是沙盒测试地址，发布到appstore后记得修改哦
//        $fp = stream_socket_client('ssl://gateway.sandbox.push.apple.com:2195', $err, $errstr, 10, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx);
        if (!$fp)
            exit("Failed to connect: $err $errstr" . PHP_EOL);

        // Create the payload body
        $body['aps'] = array(
            'alert' => $title,
            //  'badge' => intval($shuzi['shu']), //提醒数量
            'sound' => 'default',
        );
        $body['type'] = $type;
        $body['value'] = $valueArray;
        //推送方式，包括了提示内容，提示方式和提示声音

        // Encode the payload as JSON
        $payload = json_encode($body);

        // Build the binary notification
        $msg = chr(0) . pack('n', 32) . pack('H*', $device_id) . pack('n', strlen($payload)) . $payload;
        //$deviceToken
        // Send it to the server
        $result = fwrite($fp, $msg, strlen($msg));

        // Close the connection to the server
        fclose($fp);

//                if (!$result) {
//                    echo 'Message not delivered' . PHP_EOL;
//                } else {
//                   echo 'Message successfully delivered' . PHP_EOL;
//                }
    }

}