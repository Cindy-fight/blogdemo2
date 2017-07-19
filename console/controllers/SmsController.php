<?php 

namespace console\controllers;

use  yii\console\Controller;
use common\models\Comment;

class SmsController extends Controller
{
	public function actionSend()
	{
		$newComment = Comment::find()->where(['remind' => 0, 'status' => 1])->count();
		if ($newComment > 0)
		{
			$content = '有' . $newComment . '条新评论待审核！';
			$result = $this->vendorSmsService($content);
			if ($result['status'] == "success")
			{
				Comment::updateAll(['remind' => 1]);  //把提醒标志全部设为已提醒
				echo '[' . date('Y-m-d H:i:s',$result['dt']) . ']' .$content . '[' .$result['length'] . "]\r\n";
			}
			return 0;
		}
	}
	
	
	protected function vendorSmsService($content)
	{
		
		//实现第三方短信供应商提供的短信发送接口
		
// 		$username = '';
// 		$password = '';
// 		$apikey = '';  //密码
// 		$mobile = $adminuser->mobile;
		
// 		$url = 'http://sms.vendor.com/api/send/?';
// 		$data = array(
// 				'username' 	=> $username,
// 				'password' 	=> $password,
// 				'mobile' 	=> $mobile,
// 				'content'	=> $content,
// 				'apikey'	=> $apikey,
// 		);
		
// 		$result = $this->curlSend($url, $data);
// 		return $result;
		
		$result = array("status" => "success", "dt" => time(), "length" => 43); //模拟数据
		return $result;
	}
	
}