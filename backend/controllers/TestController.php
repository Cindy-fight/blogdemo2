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
		$to = "1098454719@qq.com";
		$subject = "帅哥，考虑的怎么样了";
		$content = "帅哥，考虑的怎么样了，要不要一起吃个饭!";
		
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
		
// 		$result = Util::doSendMail($to, $subject, $content);
		
		if ($sendstat)
		{
			echo '邮件发送成功!';
		}else {
			echo '邮件发送失败了';
		}
	}
}