# phpmailer-swoole

it can send mail by socks5 in phpmailer; 



how to use it


for example:


$mail = new PHPMailer(true);

$mail->isSMTP(); // tell to use smtp

$mail->CharSet = "utf-8"; // set charset to utf8

$mail->SMTPAuth = true;  // use smpt auth

$mail->SMTPSecure = "ssl";

$mail->Host = $mail_host;

$mail->Port = $mail_port;

$mail->Username = $mail_user_name;

$mail->Password = $mail_pwd;

$mail->setFrom($mail_user_name, $mail_from_name);

$mail->Subject = $subject;

$mail->MsgHTML($body);

//look here
$smtp = new \PHPMailerSwoole\PHPMailer\SMTP();
//reset phpmailer's smtp object
$mail->setSMTPInstance($smtp);

$re = $mail->send();


if u need to use socks5,just code:

$client_setting = [];
$client_setting['socks5_host'] = 127.0.0.1;
$client_setting['socks5_port'] = 1000;
$client_setting['socks5_username'] = username;
$client_setting['socks5_password'] = pass;

$smtp = new \PHPMailerSwoole\PHPMailer\SMTP();
//reset phpmailer's smtp object
$smtp->swooleSetting($client_setting);
$mail->setSMTPInstance($smtp);