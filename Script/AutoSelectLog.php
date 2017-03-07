<?php

/**
 * wwjie
 * 自动日志检测，邮件通知
 */


//设置错误等级
error_reporting(-1); //-1 所有
ini_set('display_errors', '1'); //1显示

require_once './../vendor/Deyi/PHPMailer-master/PHPMailerAutoload.php';


class AutoReadNewLogSendMessage
{


    //对应文件最后一次错误内容的md5值
    static public $last_mark = array();
    //最后一次发送邮件时间
    static public $last_send_time = 0;

    //返回有变化文件 指定行数
    public function readLogFile($log_file)
    {
        $line_num = 1000; //一次取出条数
        if (!is_file($log_file)) {
            return "";
        }
        $message_data = $this->getLastLines($log_file, $line_num);
        $md5 = md5($message_data);
        if (isset(self::$last_mark[$log_file])) {
            if (self::$last_mark[$log_file] == $md5) {
                return '';
            }
        }
        self::$last_mark[$log_file] = $md5;
        return $message_data;
    }


    /**
     * 取文件最后$n行
     * @param string $file 文件路径
     * @param int $line 最后几行
     * @return mixed false表示有错误，成功则返回字符串
     */
    public function getLastLines($file, $line = 1)
    {
        if (!$fp = fopen($file, 'r')) {
            return false;
        }
        $pos = -1;      //偏移量
        $eof = " ";     //行尾标识
        $data = "";
        while ($line > 0) {//逐行遍历
            while ($eof != "\n" && $eof !== false) { //不是行尾
                fseek($fp, $pos, SEEK_END);//fseek成功返回0，失败返回-1
                $eof = fgetc($fp);//读取一个字符并赋给行尾标识
                $pos--;//向前偏移
            }
            if ($eof === false) { //到达文件头 取出当前行并拼接 data
                $pos += 2;
                fseek($fp, $pos, SEEK_END);
                $data = fgets($fp) . $data;//读取一行
                break;
            } else {
                $eof = " ";
                $data = fgets($fp) . $data;//读取一行
                $line--;
            }
        }
        fclose($fp);
        return $data;
    }
}


function SendEmail($subject, $HTMLcontent, $file = '')
{
    $mail = new PHPMailer;
    $mail->setLanguage('ch');
    //$mail->SMTPDebug = 3;                               // Enable verbose debug output

    $mail->isSMTP();                                      // Set mailer to use SMTP
    $mail->Host = 'smtp.163.com';  // Specify main and backup SMTP servers
    $mail->SMTPAuth = true;                               // Enable SMTP authentication
    $mail->Username = 'danbus';                 // SMTP username
    $mail->Password = 'jj15926912424';                           // SMTP password
    //$mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
    //$mail->Port = 587;                                    // TCP port to connect to

    $mail->From = 'danbus@163.com';
    $mail->FromName = 'Server';
    //$mail->addAddress('joe@example.net', 'Joe User');     // Add a recipient
    $mail->addAddress('416347183@qq.com');               // 王维杰
//    $mail->addAddress('386793764@qq.com');               //范阳
    $mail->addAddress('276817277@qq.com');               // 万江
    $mail->addAddress('11942518@qq.com');               //覃涛
    $mail->addAddress('2390478115@qq.com');             //童鑫

    //$mail->addReplyTo('416347183@qq.com', 'hello');
    //$mail->addCC('cc@example.com');
    //$mail->addBCC('bcc@example.com');

    $mail->WordWrap = 1000;                                 // Set word wrap to 50 characters
    //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
    //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
    $mail->isHTML(true);                                  // Set email format to HTML

    if ($file) {
        $mail->AddAttachment($file);  //可以添加附件
    }
    $mail->Subject = $subject;
    $mail->Body = $HTMLcontent;
    $mail->CharSet = "utf-8";
    if (!$mail->send()) {
        //echo 'Message could not be sent.';
        // echo 'Mailer Error: ' . $mail->ErrorInfo;
        return false;
    } else {
        // echo 'Message has been sent';
        return true;
    }
}


$a = new AutoReadNewLogSendMessage();
$last_mark = '';
while (1) {



    //框架日志
    $zf2_error = $a->readLogFile('/data/work/web/wanfantian.com/log/' . date('Y-m-d') . '-error.log');
    //zendframework 日志
    $php_error = $a->readLogFile('/data/log/php/php_errors.log');
    $set_filename = 'set.lock';  //设置参数配置文件
    $data = ''; //发送内容
    $send = false;

    if ($php_error) {
        // PHP Warning:  PHP Fatal error:
        preg_match_all('/\[.*\].*error:.*\n/', $php_error, $matches);
//        preg_match_all('/\[.*\].*(error|Warning):.*\n/', $php_error, $matches);


        $php_error='';
        if (!empty($matches[0])) {
            foreach ($matches[0] as $v){
                $php_error.=$v;
            }
            $last_line = end($matches[0]);
            if ($last_line != $last_mark) {  //过滤后判断最后一条错误日志是否相同
                $send = true;
                $data .= $php_error;
                $last_mark = $last_line;
            }
        }
    } else {
        $data = $zf2_error;
    }

    if ($zf2_error) {
        $data .= $zf2_error;
        $send = true;
    }


    if ($send == true) {
        //发送邮件相关
        $time1 = time() + (3600 * 2);
        $time2 = time() + (3600 * 1000);
        $time3 = time();
        $html = <<< EOF
<h3>
<a href="http://wan.wanfantian.com/web/index/selectLogSet?action=set&time={$time1}">2小时后重新检测</a>
</h3>
EOF;
// <a href="http://wan.wanfantian.com/web/index/selectLogSet?action=set&time={$time2}">关闭检测</a>
//<a href="http://wan.wanfantian.com/web/index/selectLogSet?action=set&time={$time3}">开启检测</a>


        if (!is_file($set_filename)) {
            file_put_contents($set_filename, time() - 1);
        }

        $time = file_get_contents($set_filename);

        if ($time <= time()) {
            $send = true;
            //下封通知邮件为10分钟后  600
            file_put_contents($set_filename, (time() + 600));
        } else {
            $send = false;
        }


        if ($send == true) {
            $tmp_filename = 'tmp.log';
            file_put_contents($tmp_filename, $data);
            $html.="<br><pre>{$data}</pre>";
            $state = SendEmail('服务异常提醒', $html, $tmp_filename);
            if ($state) {
                echo 'send true';
            } else {
                echo 'send false';
            }
        }

    }

    sleep(300);  // 300s 5分钟执行一次日志查询

}







