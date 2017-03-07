<?php
require 'PHPMailerAutoload.php';

$mail = new PHPMailer;

$mail->setLanguage('ch');


//$mail->SMTPDebug = 3;                               // Enable verbose debug output

$mail->isSMTP();                                      // Set mailer to use SMTP
$mail->Host = 'smtp.163.com';  // Specify main and backup SMTP servers
$mail->SMTPAuth = true;                               // Enable SMTP authentication
$mail->Username = '927296416';                 // SMTP username
$mail->Password = 'JJ15926912424';                           // SMTP password
//$mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
//$mail->Port = 587;                                    // TCP port to connect to

$mail->From = '927296416@163.com';
$mail->FromName = 'Server';
//$mail->addAddress('joe@example.net', 'Joe User');     // Add a recipient
$mail->addAddress('416347183@qq.com');               // Name is optional
//$mail->addReplyTo('416347183@qq.com', 'hello');
//$mail->addCC('cc@example.com');
//$mail->addBCC('bcc@example.com');

$mail->WordWrap = 50;                                 // Set word wrap to 50 characters
//$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
//$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
$mail->isHTML(true);                                  // Set email format to HTML

$mail->Subject = '此邮件来自服务器';
$mail->Body    = '这里是内容';

if(!$mail->send()) {
    echo 'Message could not be sent.';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
} else {
    echo 'Message has been sent';
}
