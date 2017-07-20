<?php

namespace common\library;

use Yii;
use yii\helpers\ArrayHelper;
use common\extension\EasyMail;

class Util{
	
	/*
	 * 设置Cache
	 * @param String $key
	 * @param String $val
	 * @return boolean
	 */
	public static function setCache($key, $val, $time=86400000){
		$res = false;
		try {
			\Yii::$app->cache->set($key, $val, $time);
			$res = true;
		} catch (Exception $e) {}
		return $res;
	}
	
	/*
	 * 根据Key获取cache内容
	 * @param String $key	cache的Key
	 * @param boolean $toArray	是否将结果转化为数组形式
	 * @return mixed
	 */
	public static function fetchCache($key, $toArray=false){
		$res = array();
		try {
			$res = \Yii::$app->cache->get($key);
			if ($toArray) {
				$res = json_decode($res, TRUE);
			}
		} catch (Exception $e) {}
		return $res;
	}
	
	/*
	 * 根据Key删除cache内容
	 * @param String $key	cache的Key
	 */
	public static function deleteCache($key){
		try {
			\Yii::$app->cache->delete($key);
			return true;
		} catch (Exception $e) {}
		return false;
	}
	
	/*
	 * 发出一个Get请求
	 * @param String $url	请求的地址
	 */
	public static function getRequest($url = null){
		if(empty($url)){
			return false;
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
	
	/*
	 * 发出一个POST请求
	 * @param String $url	请求的地址
	 * @param String $data	数据
	 */
	public static function postRequest($url = null, $data, $header=array(),$timeout=0){
		if (empty($url)){
			return false;
		}
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($ch,CURLOPT_COOKIEJAR,null);
		if ($header) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		}
		if ($timeout && is_numeric($timeout) && $timeout > 0) {
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		}
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_POST,true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
		$content = curl_exec($ch);
		return $content;
	}
	
	/*
	 * 判断字符串是否有效
	 * @param string
	 * @return true is valid,false is not valid
	 */
	public static function isValid($string){
		$string = trim($string);
		if(empty($string)){
			return false;
		}else{
			return true;
		}
	}
	
	/*
	 * 记录日志
	 * @param contents
	 */
	public static function log($contents){
		$file   = '/tmp/blogdemo2.log';
		$handle = fopen($file, 'a+');
		fwrite($handle, $contents."\n");
		fclose($handle);
	}
	
	/*
	 * 获取当前时间
	 * format: Y-m-d H:i:s
	 */
	public static function getNowTime(){
		return date('Y-m-d H:i:s');
	}
	
	/*
	 * 是否是正确的日期
	 * yyyy-mm-dd HH:MM:ss 或者 yyyy-mm-dd
	 */
	public static function isDate($date){
		if (preg_match("/^\d\d\d\d-\d\d-\d\d\s\d\d\:\d\d:\d\d$/", $date) || preg_match("/^\d\d\d\d-\d\d-\d\d$/", $date)) {
			return true;
		}
		return false;
	}
	
	/*
	 * 获取IP地址
	 */
	public static function getClientIp(){
		return (@$_SERVER["HTTP_X_REAL_IP"] != null) ? @$_SERVER["HTTP_X_REAL_IP"] : @$_SERVER["REMOTE_ADDR"];
	}
	
	/*
	 * 从数组中取值
	 * @param String $key	cache的Key
	 * @param Array $result
	 * @param String $key
	 * @return String $value
	 */
	public static function getValuesFromArray($result,$key,$defaultValue=null){
		$value = null;
		$key = trim($key);
		try {
			if($key === null){
				$value = null;
			}elseif($key === ''){
				$value = null;
			}elseif(substr($key, 0, 1) === '@'){
				$value = substr($key, 1);
			}else{
				$value = ArrayHelper::getValue($result, $key, $defaultValue);
			}
		} catch (\Exception $e) {
			self::log($e->getMessage());
		}
		return $value;
	}
	
	/*
	 * 将数字转化位固定的位数
	 * @param unknown_type $number
	 * @param unknown_type $size
	 */
	public static function formatNumToSize($number){
		$res = '';
		if (is_numeric($number)) {
			if ($number >= 1073741824) {
				$res = sprintf('%.2f', $number / 1073741824) . 'GB';
			}elseif ($number >= 1048576){
				$res = sprintf('%.2f', $number / 1048576) . 'MB';
			}elseif ($number >= 1024){
				$res = sprintf('%.2f', $number / 1024) . 'KB';
			}else{
				$res = sprintf('%.2f', $number) . 'B';
			}
		}
		return $res;
	}
	
	/*
	 * 获取参数
	 * @param String $key
	 * @return String $value
	 */
	public static function getParam($key,$default = NULL){
		$request    = \Yii::$app->getRequest();
		$value      = $request->get($key);
		if(!isset($value)){
			$value  = $request->post($key);
		}
		if(!isset($value)){
			$value  = $default;
		}
		return $value;
	}
	
	/* 
	 * 是否是ajax请求 
	 */
	public static function isAjaxRequest(){
		return \Yii::$app->request->isAjax ? true : false;
	}
	
	/*
	 * get utf-8 string
	 * detect and transfer
	 */
	public static function getUtf8String($string){
		$encode = mb_detect_encoding($string, array('ASCII','GB2312','GBK','UTF-8'));
		if ($encode == 'GB2312') {
			$string = iconv("GB2312","UTF-8",$string);
		}elseif ($encode == 'GBK'){
			$string = iconv("GBK","UTF-8",$string);
		}elseif ($encode == 'ASCII'){
			$string = iconv("ASCII","UTF-8",$string);
		}
		return $string;
	}
	
	/*
	 * 读取请求的参数
	 * @param	string	$key
	 * @param	string	$default = 0
	 */
	public static function _request($key,$default = 0){
		if ($key) {
			return isset($_REQUEST[$key]) && $_REQUEST[$key] ? $_REQUEST[$key] : $default;
		}
		return null;
	}
	
	/*
	 * send email
	 */
	public static function doSendMail($to, $subject, $content){
		$account1=array(
				"email"		=> EMAIL_ADDR,
				"name" 		=> EMAIL_ACCOUNT_NAME,
				"type"		=> '0',
				"host" 		=> SMTP_HOST,
				"port" 		=> SMTP_PORT,
				"usr"  		=> SMTP_USER,
				"pass" 		=> SMTP_PWD,
				"from"		=> array(EMAIL_ADDR,EMAIL_ACCOUNT_NAME),
		);
		$mail = array(
				'reply-to'		=> array(EMAIL_ADDR,EMAIL_ACCOUNT_NAME),
				"dst"			=> "utf8",
				"subject"		=> $subject,
				'html'			=> $content,
				"from"			=> array(EMAIL_ADDR,EMAIL_ACCOUNT_NAME),
				"to"			=> $to,
				"return_path"	=> SMTP_RETURN,
				"src_encoding"	=> "utf-8",
		);
		$genMail = EasyMail::write_mail($mail);
		$sendstat = EasyMail::send($account1, $genMail);
		return $sendstat;
	}
	
}