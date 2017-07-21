<?php

namespace backend\controllers;

use yii\web\Controller;
use common\extension\EasyMail;
use common\library\Util;

class TestController extends Controller
{
	public function actionIndex()
	{
		echo 'Hello World!';
	}
	
	public function actionTest()
	{
		$number = '125355357';
		$result = Util::formatNumToSize($number);
		echo $result;
	}
	
	public function actionSend()
	{
		$to = "592557247@qq.com";
		$subject = "Hello";
		$content = "Hello World!";
		
		$account1=array(
				"email"		=> 'no-reply@maimob.cn',
				"name" 		=> 'maimob',
				"type"		=> '0',
				"host" 		=> 'smtp.exmail.qq.com',
				"port" 		=> 25,
				"usr"  		=> 'no-reply@maimob.cn',
				"pass" 		=> 'mm13ab',
				"from"		=> array('no-reply@maimob.cn','maimob'),
		);
		$mail = array(
				'reply-to'		=> array('no-reply@maimob.cn','maimob'),
				"dst"			=> "utf8",
				"subject"		=> $subject,
				'html'			=> $content,
				"from"			=> array('no-reply@maimob.cn','maimob'),
				"to"			=> $to,
				"return_path"	=> 'no-reply@maimob.cn',
				"src_encoding"	=> "utf-8",
		);
		
		$genMail = EasyMail::write_mail($mail);
		$sendstat = EasyMail::send($account1, $genMail);
		
// 		$result = Util::doSendMail($to, $subject, $content);  //同样的程序调用 总是显示邮件发送失败
		
		if ($sendstat)
		{
			echo '邮件发送成功!';
		}else {
			echo '邮件发送失败了';
		}
	}
}