<?php
namespace common\extension;

class EasyMail {
	private static function smtp_data_encode($msg_data) {
		$s = "";
		
/* the server is ready to accept data!
 * according to rfc 821 we should not send more than 1000
 * including the CRLF
 * characters on a single line so we will break the data up
 * into lines by \r and/or \n then if needed we will break
 * each of those into smaller lines to fit within the limit.
 * in addition we will be looking for lines that start with
 * a period '.' and append and additional period '.' to that
 * line. NOTE: this does not count towards limit.
 */
		
		// normalize the line breaks so we know the explode works
		$msg_data = str_replace ( "\r\n", "\n", $msg_data );
		$msg_data = str_replace ( "\r", "\n", $msg_data );
		$lines = explode ( "\n", $msg_data );
		
/* we need to find a good way to determine is headers are
 * in the msg_data or if it is a straight msg body
 * currently I am assuming rfc 822 definitions of msg headers
 * and if the first field of the first line (':' sperated)
 * does not contain a space then it _should_ be a header
 * and we can process all lines before a blank "" line as
 * headers.
 */
		
		$field = substr ( $lines [0], 0, strpos ( $lines [0], ":" ) );
		$in_headers = false;
		if (! empty ( $field ) && ! strstr ( $field, " " )) {
			$in_headers = true;
		}
		
		$max_line_length = 998; // used below; set here for ease in change
		

		while ( list ( , $line ) = @each ( $lines ) ) {
			$lines_out = null;
			if ($line == "" && $in_headers) {
				$in_headers = false;
			}
			// ok we need to break this line up into several smaller lines
			while ( strlen ( $line ) > $max_line_length ) {
				$pos = strrpos ( substr ( $line, 0, $max_line_length ), " " );
				
				// Patch to fix DOS attack
				if (! $pos) {
					$pos = $max_line_length - 1;
					$lines_out [] = substr ( $line, 0, $pos );
					$line = substr ( $line, $pos );
				} else {
					$lines_out [] = substr ( $line, 0, $pos );
					$line = substr ( $line, $pos + 1 );
				}
				
/* if processing headers add a LWSP-char to the front of new line
 * rfc 822 on long msg headers
 */
				if ($in_headers) {
					$line = "\t" . $line;
				}
			}
			$lines_out [] = $line;
			
			// send the lines to the server
			while ( list ( , $line_out ) = @each ( $lines_out ) ) {
				if (strlen ( $line_out ) > 0) {
					if (substr ( $line_out, 0, 1 ) == ".") {
						$line_out = "." . $line_out;
					}
				}
				$s .= $line_out . "\r\n";
			}
		}
		return $s;
	}
	
	private static function smtp_send_cmd($sock, $cmd) {
		$print_command = false;//(defined("DEBUG")&& DEBUG );
		if (is_string ( $cmd )) {
			if ($print_command) {
				echo ">>>>>>>>>>>>>\n";
				echo "$cmd\n";
			}
			if (fputs ( $sock, $cmd . "\r\n" ) === false) {
				if ($print_command) {
					echo "#ERROR WRITE SOCK FAIL\n";
				}
				return 1;
			}
		}
		if ($print_command) {
			echo "<<<<<<<<<<<<<\n";
		}
		$s = fread ( $sock, 65535 );
		if ($s === false) {
			if ($print_command) {
				echo "#ERROR READ SOCK FAIL\n";
			}
			return 2;
		}
		if ($print_command) {
			echo "$s";
		}
		if ($cmd == "QUIT") {
			fclose ( $sock );
			return 0;
		}
		$lines = explode ( "\r\n", $s );
		array_pop ( $lines );
		$response = array_pop ( $lines );
		if (! ($response && preg_match ( "{^[23]}", $response ))) {
			fputs ( $sock, "QUIT\r\n" );
			fgets ( $sock, 512 );
			fclose ( $sock );
			return 3;
		}
		return 0;
	}
	
	static function get_mx_record($sender, $host = false) {
		static $mx_cache = array ();
		if ($host === false) {
			$host = $sender;
			$sender = array ();
		}
		if (key_exists ( $host, $mx_cache )) {
			return $mx_cache [$host];
		}
		if (@$sender ["dba"]) {
			#if set dba, then read db to get mx
			$dba = $sender ["dba"];
			$record = $dba->select_one ( "select ip from #PREFIX#mx_cache where update_time>unix_timestamp() and domain =?", $host );
			if ($record) {
				$mx_cache [$host] = $record;
				return $record;
			}
		}
		$record = false;
		if (getmxrr ( $host, $mx, $weight )) {
			$min_key = false;
			$min_val = 100000;
			foreach ( $weight as $k => $v ) {
				if ($v < $min_val) {
					$min_val = $v;
					$min_key = $k;
				}
			}
			if ($min_key !== false) {
				$record = gethostbynamel ( $mx [$min_key] );
				if ($record) {
					$record = $record [0];
				}
			}
			if ($record) {
				$mx_cache [$host] = $record;
				if (@$sender ["dba"]) {
					$dba = $sender ["dba"];
					$dba->execute ( "replace into #PREFIX#mx_cache values (?,?,unix_timestamp()+86400)", $host, $record );
				}
			}
		}
		return $record;
	}
	
	/*
 * 0 email is not exist
 * 1 email exist
 * false something error
 */
	
	static function is_email_exists($email) {
		$email = trim ( $email );
		if (! preg_match ( "{^" . self::email_regex () . "$}", $email )) {
			return 0;
		}
		$ip = self::get_mx_record ( array (), substr ( $email, strpos ( $email, '@' ) + 1 ) );
		if (! $ip) {
			return 0;
		}
		$sock = @fsockopen ( $ip, 25 );
		if (! $sock) {
			return 0;
		}
		$ret = self::smtp_send_cmd ( $sock, false );
		if ($ret != 0) {
			return false;
		}
		$ret = self::smtp_send_cmd ( $sock, "EHLO msn.com" );
		if ($ret != 0) {
			return false;
		}
		$ret = self::smtp_send_cmd ( $sock, "MAIL FROM: <gates@msn.com>" );
		if ($ret != 0) {
			return false;
		}
		$ret = self::smtp_send_cmd ( $sock, "RCPT TO: <" . $email . ">" );
		if ($ret != 0) {
			return 0;
		}
		self::smtp_send_cmd ( $sock, "QUIT" );
		return 1;
	}
	
	static function is_email($email) {
		if (preg_match ( "{^\\s*" . self::email_regex () . "\\s*$}", $email )) {
			return true;
		} else {
			return false;
		}
	}
	
	private static function _replace_or_append_head($mail, $key, $new_v, $line_delimiter) {
		$new_header = self::_gen_header ( $key, $new_v, $line_delimiter );
		$key = trim ( strtolower ( $key ) );
		$emails = array ();
		if (preg_match ( "{(?=(?:^|\\r|\\n))[\\r\\n]*(\\x20*" . preg_quote ( $key ) . "\\s*:([^\\r\\n]*)\\r?\\n)}i", $mail, $m )) {
			$mail = str_replace ( $m [1], $new_header, $mail );
		} else {
			$mail = $new_header . $mail;
		}
		return $mail;
	}
	
	private static function send_direct($sender, $mail, $to_list) {
		$host_email = array ();
		foreach ( $to_list as $email ) {
			$host = substr ( $email, strpos ( $email, '@' ) + 1 );
			if (! isset ( $host_email [$host] )) {
				$host_email [$host] = array ();
			}
			$host_email [$host] [] = $email;
		}
		$succ = true;
		foreach ( $host_email as $k => $v ) {
			$host = self::get_mx_record ( $sender, $k );
			if (self::smtp_send ( $host, 25, false, false, $mail, @$sender ["from_email"], $v ) != 0) {
				$succ = false;
			}
		}
		return $succ;
	}
	
	private static function send_smtp($sender, $mail, $to_list){
		$succ 	= true;
		$port	= intval(trim(@$sender["port"]));
		$port	= ($port>0 && $port<65536 ) ? $port : 25;
		$smtpSendFlg = self::smtp_send ( $sender["host"],$port, @$sender["usr"], @$sender["pass"], $mail, @$sender ["from_email"], $to_list );
		if ($smtpSendFlg != 0) {
			//echo "smtpSendFlg is :{$smtpSendFlg}<br>";
			$succ = false;
		}
		//echo $succ ? "succ is true<br>" : "succ is false<br>";
		return $succ;
	}
	
	private static function parse_email_from_header($mail, $key) {
		$key = trim ( strtolower ( $key ) );
		$emails = array ();
		if (preg_match ( "{(?=(?:^|\\r|\\n))([\\r\\n]*)\\x20*" . preg_quote ( $key ) . "\\s*:([^\\r\\n]*)\\r?\\n}i", $mail, $m )) {
			foreach ( explode ( ",", $m[2] ) as $entry ) {
				$entry = trim ( $entry );
				if (preg_match ( "{^\\s*(" . self::email_regex() . ")\\s*$}s", $entry, $m )) {
					$emails [] = $m[1];
				} elseif (preg_match ( "{^\\s*\"?\\s*(.*?)\\s*\"?\\s*<\\s*(.*)\\s*>\\s*$}s", $entry, $m )) {
					$emails [] = $m[2];
				} else {
					//echo "skip\n";
					#skip
				}
				//Util::dump($entry);exit;
			}
		}
		return $emails;
	}
	
	public static function email_regex() {
		return "^\w+[\._\-\w+]*@\w+[\._\-\w+]+$";
	}
	
	private static function smtp_send($host, $port, $usr, $pass, $mail, $from_email, $to_list) {
		//echo "begin socket send<br>";
		$sock = @fsockopen ( $host, $port );
		//echo "begin socket send:open socket<br>";
		if (! $sock) {
			return 1;
		}
		//echo "begin socket send:use socket<br>";
		//Util::dump($sock);
		$ret = self::smtp_send_cmd ( $sock, false );
		if ($ret != 0) {
			//echo "ret is:{$ret}<br>";
			return 11;
		}
		//echo "begin socket send:EHLO<br>";
		$ret = self::smtp_send_cmd ( $sock, "EHLO " . substr ( $from_email, strpos ( $from_email, '@' ) + 1 ) );
		if ($ret != 0) {
			return 2;
		}
		if ($usr) {
			$ret = self::smtp_send_cmd ( $sock, "AUTH LOGIN " . base64_encode ( $usr ) );
			if ($ret != 0) {
				return 3;
			}
			$ret = self::smtp_send_cmd ( $sock, base64_encode ( $pass ) );
			if ($ret != 0) {
				return 4;
			}
		}
		$ret = self::smtp_send_cmd ( $sock, "MAIL FROM: <" . $from_email . ">" );
		//echo "<pre>";
		//die ( __LINE__ . "@" . __FILE__ . ":" . print_r($from_email) . $ret);
		//echo "</pre>";
		if ($ret != 0) {
			return 5;
		}
		foreach ( $to_list as $email ) {
			$ret = self::smtp_send_cmd ( $sock, "RCPT TO: <" . $email . ">" );
			if ($ret != 0) {
				return 6;
			}
		}
		$ret = self::smtp_send_cmd ( $sock, "DATA" );
		if ($ret != 0) {
			return 7;
		}
		
		$ret = self::smtp_send_cmd ( $sock, self::smtp_data_encode ( $mail ) . "\r\n." );
		if ($ret != 0) {
			return 8;
		}
		
		self::smtp_send_cmd ( $sock, "QUIT" );
		return 0;
	}
	
	#=============================================================
	#===guess file content type from it's name
	private function guess_file_content_type($filename) {
		$map = array ('au' => 'audio/basic', 'avi' => 'video/x-msvideo', 'class' => 'application/octet-stream', 'cpt' => 'application/mac-compactpro', 'dcr' => 'application/x-director', 'dir' => 'application/x-director', 'doc' => 'application/msword', 'exe' => 'application/octet-stream', 'gif' => 'image/gif', 'gtx' => 'application/x-gentrix', 'jpeg' => 'image/jpeg', 'jpg' => 'image/jpeg', 'js' => 'application/x-javascript', 'hqx' => 'application/mac-binhex40', 'htm' => 'text/html', 'html' => 'text/html', 'mid' => 'audio/midi', 'midi' => 'audio/midi', 'mov' => 'video/quicktime', 'mp2' => 'audio/mpeg', 'mp3' => 'audio/mpeg', 'mpeg' => 'video/mpeg', 'mpg' => 'video/mpeg', 'pdf' => 'application/pdf', 'pm' => 'text/plain', 'pl' => 'text/plain', 'ppt' => 'application/powerpoint', 'ps' => 'application/postscript', 'qt' => 'video/quicktime', 'ram' => 'audio/x-pn-realaudio', 'rtf' => 'application/rtf', 'tar' => 'application/x-tar', 'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'txt' => 'text/plain', 'wav' => 'audio/x-wav', 'xbm' => 'image/x-xbitmap', 'zip' => 'application/zip' );
		$type = strtolower ( pathinfo ( $filename, PATHINFO_EXTENSION ) );
		if (key_exists ( $type, $map )) {
			return $map [$type];
		} else {
			return 'application/octet-stream';
		}
	}
	
	private static function _gen_header($k, $v, $line_delimiter) {
		if ($v === false) {
			return "";
		}
		return "$k: $v$line_delimiter";
	}
	
	private static function _encode_b($str, $encoding) {
		return '=?' . $encoding . '?B?' . base64_encode ( $str ) . '?=';
	}
	
	private static function _change_encoding($str, $src_encoding, $dst_encoding) {
		if (! $src_encoding) {
			return $str;
		} else {
			return @iconv ( $src_encoding, $dst_encoding . "//IGNORE", $str );
		}
	}
	
	private static function _encode_header($str, $src_encoding, $dst_encoding, $dst_encoding_txt) {
		#change encoding
		$str = self::_change_encoding ( $str, $src_encoding, $dst_encoding );
		if (! preg_match ( "/^[\040-\176]*$/", $str )) {
			$str = self::_encode_b ( $str, $dst_encoding_txt );
		}
		return $str;
	}
	
	private static function _chunk_split($str, $line_delimiter = "\r\n", $line_len = 72) {
		$len = strlen ( $str );
		$out = '';
		while ( $len > 0 ) {
			if ($len >= $line_len) {
				$out .= substr ( $str, 0, $line_len ) . $line_delimiter;
				$str = substr ( $str, $line_len );
				$len = $len - $line_len;
			} else {
				$out .= $str . $line_delimiter;
				$str = '';
				$len = 0;
			}
		}
		return $out;
	}
	
	private static function _gen_date() {
		return date ( 'D, d M Y H:i:s O' );
	}
	
	private static function _is_email_name_pair_str($email_name_pair) {
		if (! is_string ( $email_name_pair )) {
			return false;
		}
		if (preg_match ( "{^\\s*(" . self::email_regex () . ")\\s*$}s", $email_name_pair )) {
			return true;
		} elseif (preg_match ( "{^\\s*\"?\\s*(.*?)\\s*\"?\\s*<\\s*(" . self::email_regex () . ")\\s*>\\s*$}s", $email_name_pair )) {
			return true;
		} else {
			return false;
		}
	
	}
	
	private static function _parse_email_name_pair($email_name_pair) {
		if (is_string ( $email_name_pair ) && preg_match ( "{^\\s*(" . self::email_regex () . ")\\s*$}s", $email_name_pair, $m )) {
			return array ($m [1], null );
		} elseif (is_string ( $email_name_pair ) && preg_match ( "{^\\s*\"?\\s*(.*?)\\s*\"?\\s*<\\s*(" . self::email_regex () . ")\\s*>\\s*$}s", $email_name_pair, $m )) {
			return array ($m [2], $m [1] );
		} elseif (is_array ( $email_name_pair ) && count ( $email_name_pair ) == 1) {
			return array (trim ( $email_name_pair [0] ), null );
		} elseif (is_array ( $email_name_pair ) && count ( $email_name_pair ) == 2) {
// 			$email = isset($email_name_pair [0]) ? $email_name_pair [0] : (isset($email_name_pair['email']) ? $email_name_pair['email'] : 'yongjin.xu@maimob.cn');
			$email = isset($email_name_pair [0]) ? $email_name_pair [0] : (isset($email_name_pair['email']) ? $email_name_pair['email'] : 'shiyuan.wu@maimob.cn');
			$name = isset($email_name_pair [1]) ? $email_name_pair [1] : (isset($email_name_pair['name']) ? $email_name_pair['name'] : 'JavaXu');
			return array (trim ( $email ), trim ( $name ) );
		} else {
			die ( __LINE__ . "@" . __FILE__ . ":" . "email cannot parse" );
		}
	}
	
	private static function _gen_email_name_pair($email_name_pair, $src_encoding, $dst_encoding, $dst_encoding_txt) {
		list ( $email, $name ) = $email_name_pair;
		if (strlen ( ( string ) $name ) == 0) {
			return $email;
		}
		$name = self::_encode_header ( $name, $src_encoding, $dst_encoding, $dst_encoding_txt );
		$name = preg_replace ( "{([\\\\\"])}", '\\\\$1', $name );
		return "\"$name\" <$email>";
	}
	
	#please use simple char in file_path and file_name
	private static function _process_file($file) {
		$attachment = array ();
		if (isset ( $file ["file_bin"] ) && isset ( $file ["file_path"] )) {
			die ( __LINE__ . "@" . __FILE__ . ":" . "file_bin and file_path can only set one" );
		} elseif (isset ( $file ["file_path"] )) {
			$file_bin = @file_get_contents ( $file ["file_path"] );
			if ($file_bin === false) {
				die ( __LINE__ . "@" . __FILE__ . ":" . "cannot open file " . $file ["file_path"] );
			}
			$attachment ["file_bin"] = $file_bin;
			if (isset ( $file ["file_name"] )) {
				$attachment ["file_name"] = trim ( $file ["file_name"] );
			} else {
				$attachment ["file_name"] = pathinfo ( $file ["file_path"], PATHINFO_BASENAME );
			}
		} elseif (isset ( $file ["file_bin"] )) {
			$attachment ["file_bin"] = $file ["file_bin"];
			$attachment ["file_name"] = trim ( @$file ["file_name"] );
			if ($attachment ["file_name"] === "") {
				unset ( $attachment ["file_name"] );
			}
		} else {
			die ( __LINE__ . "@" . __FILE__ . ":" . "must set one of file_bin and file_path" );
		}
		
		#===if u don't set file_name please set content_type
		if (isset ( $file ["content_type"] )) {
			$attachment ["content_type"] = $file ["content_type"];
		} elseif (isset ( $attachment ["file_name"] )) {
			$attachment ["content_type"] = self::guess_file_content_type ( $attachment ["file_name"] );
		} else {
			die ( __LINE__ . "@" . __FILE__ . ":" . "if u don't set file_name please set content_type" );
		}
		
		if (isset ( $file ["content_id"] )) {
			$attachment ["content_id"] = $file ["content_id"];
			$attachment ["content_disposion"] = 'inline';
			//unset ( $attachment ["file_name"] );
		} else {
			$attachment ["content_disposion"] = 'attachment';
			#===attachment must have a file name
			if (! isset ( $attachment ["file_name"] )) {
				die ( __LINE__ . "@" . __FILE__ . ":" . "please set file_name" );
			}
		}
		return $attachment;
	}
	
	private static function _gen_part_file($file, $src_encoding, $dst_encoding, $dst_encoding_txt, $line_delimiter) {
		$str = '';
		$file_name_str = null;
		if (is_string ( @$file ["file_name"] ) && strlen ( $file ["file_name"] ) > 0) {
			$file_name_str = self::_encode_header ( $file ["file_name"], $src_encoding, $dst_encoding, $dst_encoding_txt );
		}
		$str .= self::_gen_header ( "Content-Type", $file ["content_type"] . ";" . ($file_name_str === null ? "" : " name=\"$file_name_str\""), $line_delimiter );
		$str .= self::_gen_header ( "Content-Transfer-Encoding", "base64", $line_delimiter );
		if (is_string ( @$file ["content_id"] ) && strlen ( $file ["content_id"] ) > 0) {
			$str .= self::_gen_header ( "Content-ID", "<" . $file ["content_id"] . ">", $line_delimiter );
		}
		$str .= self::_gen_header ( "Content-Disposition", $file ["content_disposion"] . ";" . ($file_name_str === null ? "" : " filename=\"$file_name_str\""), $line_delimiter );
		$str .= $line_delimiter;
		$str .= self::_chunk_split ( base64_encode ( $file ["file_bin"] ), $line_delimiter );
		$str .= $line_delimiter;
		return $str;
	}
	
	private static function _gen_part_text($type, $text, $src_encoding, $dst_encoding, $dst_encoding_txt, $line_delimiter) {
		$str = "";
		$type = "html";
		$text = self::_change_encoding ( $text, $src_encoding, $dst_encoding );
		if (preg_match ( '{^[\\000-\\177]*$}s', $text )) {
			$dst_encoding_txt = "us-ascii";
		}
		$str .= self::_gen_header ( 'Content-Type', "text/$type; charset=$dst_encoding_txt;", $line_delimiter );
		$str .= self::_gen_header ( "Content-Transfer-Encoding", "base64", $line_delimiter );
		$str .= $line_delimiter;
		$str .= self::_chunk_split ( base64_encode ( $text ), $line_delimiter );
		$str .= $line_delimiter;
		return $str;
	}
	
	static function write_mail($mail) {
		$s = "";
		$line_delimiter = "\r\n";
		$mime_boundary_prefix = '------------06010007000403080202';
		
		#parse dst
		if (@$mail ["dst"] == "utf8" || @$mail ["dst"] == "un") {
			$dst_encoding = "utf-8";
			$dst_encoding_txt = $dst_encoding;
		} elseif (@$mail ["dst"] == "jp") {
			$dst_encoding = 'iso-2022-jp';
			$dst_encoding_txt = $dst_encoding;
		} elseif (@$mail ["dst"] == "cn") {
			$dst_encoding = 'gbk';
			$dst_encoding_txt = 'gb2312';
		} else {
			$dst_encoding = 'gbk';
			$dst_encoding_txt = 'gb2312';
		}
		$src_encoding = @$mail ["src_encoding"];
		
		#header Return-Path
		if (isset ( $mail ["return_path"] )) {
			//$s .= self::_gen_header ( "Return-Path", $mail ["return_path"], $line_delimiter );
			$s .= self::_gen_header ( "Return-Path",self::_gen_email_name_pair ( self::_parse_email_name_pair ( $mail ["from"] ), $src_encoding, $dst_encoding, $dst_encoding_txt ), $line_delimiter );
		}
		#header From
		if (isset ( $mail ["from"] )) {
			$s .= self::_gen_header ( "From", self::_gen_email_name_pair ( self::_parse_email_name_pair ( $mail ["from"] ), $src_encoding, $dst_encoding, $dst_encoding_txt ), $line_delimiter );
		}
		
		#header reply-to 
		if (isset ( $mail ["reply-to"] )) {
			$s .= self::_gen_header ( "reply-to", self::_gen_email_name_pair ( self::_parse_email_name_pair ( $mail ["reply-to"] ), $src_encoding, $dst_encoding, $dst_encoding_txt ), $line_delimiter );
		}
		
		#header To
		if (isset ( $mail ["to"] )) {
			$email_list = $mail ["to"];
			if (is_string ( $email_list )) {
				$email_list = array ($email_list );
			}
			$strs = array ();
			foreach ( $email_list as $email ) {
				$strs [] = self::_gen_email_name_pair ( self::_parse_email_name_pair ( $email ), $src_encoding, $dst_encoding, $dst_encoding_txt );
			}
			if (count ( $strs ) > 0) {
				$s .= self::_gen_header ( "To", implode ( ",", $strs ), $line_delimiter );
			}
		}
		#header CC
		if (isset ( $mail ["cc"] )) {
			$email_list = $mail ["cc"];
			if (is_string ( $email_list ) || (count ( $email_list ) == 2 && ! self::_is_email_name_pair_str ( $email_list [1] ))) {
				$email_list = array ($email_list );
			}
			$strs = array ();
			foreach ( $email_list as $email ) {
				$strs [] = self::_gen_email_name_pair ( self::_parse_email_name_pair ( $email ), $src_encoding, $dst_encoding, $dst_encoding_txt );
			}
			if (count ( $strs ) > 0) {
				$s .= self::_gen_header ( "CC", implode ( ",", $strs ), $line_delimiter );
			}
		}
		
		
		#header BCC
		if (isset ( $mail ["bcc"] )) {
			$email_list = $mail ["bcc"];
			if (is_string ( $email_list ) || (count ( $email_list ) == 2 && ! self::_is_email_name_pair_str ( $email_list [1] ))) {
				$email_list = array ($email_list );
			}
			$strs = array ();
			foreach ( $email_list as $email ) {
				$strs [] = self::_gen_email_name_pair ( self::_parse_email_name_pair ( $email ), $src_encoding, $dst_encoding, $dst_encoding_txt );
			}
			if (count ( $strs ) > 0) {
				$s .= self::_gen_header ( "BCC", implode ( ",", $strs ), $line_delimiter );
			}
		}
		#header Subject
		if (isset ( $mail ["subject"] )) {
			$s .= self::_gen_header ( "Subject", self::_encode_header ( $mail ["subject"], $src_encoding, $dst_encoding, $dst_encoding_txt ), $line_delimiter );
		}
		#header Date
		$s .= self::_gen_header ( "Date", self::_gen_date (), $line_delimiter );
		
		#header MIME-Version
		$s .= self::_gen_header ( 'MIME-Version', '1.0', $line_delimiter );
		
		#header X-Mailer
		if (isset ( $mail ["mailer"] )) {
			$s .= self::_gen_header ( "X-Mailer", $mail ["mailer"], $line_delimiter );
		} else {
			$s .= self::_gen_header ( "X-Mailer", 'Microsoft Office Outlook, Build 11.0.5510', $line_delimiter );
			$s .= self::_gen_header ( "X-MimeOLE", 'Produced By Microsoft MimeOLE V6.00.3790.181', $line_delimiter );
		}
		
		$html 	= isset($mail["html"]) ? $mail["html"] : '';//@$mail["html"];
		$plain 	= isset($mail["text"]) ? $mail["text"] : '';
		
		$mixed_files 	= array ();
		$related_files 	= array ();
		if (isset ( $mail ["files"] )) {
			foreach ( $mail ["files"] as $f ) {
				$file = self::_process_file ( $f );
				if (isset ( $file ["content_id"] )) {
					$related_files [] = $file;
				} else {
					$mixed_files [] = $file;
				}
			}
		}
		
		$has_part_related = (count ( $related_files ) != 0);
		$has_part_mixed = (count ( $mixed_files ) != 0);
		
		$mime_boundary_count = 0;
		
		if ($html === null && $plain !== null) {
			$body = self::_gen_part_text ( "plain", $plain, $src_encoding, $dst_encoding, $dst_encoding_txt, $line_delimiter );
		} elseif ($html !== null && $plain === null) {
			$body = self::_gen_part_text ( "html", $html, $src_encoding, $dst_encoding, $dst_encoding_txt, $line_delimiter );
		} else {
			$mime_boundary = $mime_boundary_prefix . (++ $mime_boundary_count);
			$body = self::_gen_header ( 'Content-Type', 'multipart/alternative; boundary="' . $mime_boundary . '"', $line_delimiter );
			$body .= $line_delimiter;
			if (! $has_part_related && ! $has_part_mixed) {
				$body .= 'This is a multi-part message in MIME format.' . $line_delimiter . $line_delimiter;
			}
			$body .= "--" . $mime_boundary . $line_delimiter;
			$body .= self::_gen_part_text ( "html", $html, $src_encoding, $dst_encoding, $dst_encoding_txt, $line_delimiter );
			$body .= "--" . $mime_boundary . $line_delimiter;
			$body .= self::_gen_part_text ( "plain", $plain, $src_encoding, $dst_encoding, $dst_encoding_txt, $line_delimiter );
			$body .= "--" . $mime_boundary . "--" . $line_delimiter . $line_delimiter;
		}
		
		if ($has_part_related) {
			$tmp = $body;
			$mime_boundary = $mime_boundary_prefix . (++ $mime_boundary_count);
			$body = self::_gen_header ( 'Content-Type', 'multipart/related; boundary="' . $mime_boundary . '"', $line_delimiter );
			$body .= $line_delimiter;
			if (! $has_part_mixed) {
				$body .= 'This is a multi-part message in MIME format.' . $line_delimiter . $line_delimiter;
			}
			$body .= "--" . $mime_boundary . $line_delimiter;
			$body .= $tmp;
			foreach ( $related_files as $file ) {
				$body .= "--" . $mime_boundary . $line_delimiter;
				$body .= self::_gen_part_file ( $file, $src_encoding, $dst_encoding, $dst_encoding_txt, $line_delimiter );
			}
			$body .= "--" . $mime_boundary . "--" . $line_delimiter . $line_delimiter;
		}
		
		if ($has_part_mixed) {
			$tmp = $body;
			$mime_boundary = $mime_boundary_prefix . (++ $mime_boundary_count);
			$body = self::_gen_header ( 'Content-Type', 'multipart/mixed; boundary="' . $mime_boundary . '"', $line_delimiter );
			$body .= $line_delimiter;
			$body .= 'This is a multi-part message in MIME format.' . $line_delimiter . $line_delimiter;
			$body .= "--" . $mime_boundary . $line_delimiter;
			$body .= $tmp;
			foreach ( $mixed_files as $file ) {
				$body .= "--" . $mime_boundary . $line_delimiter;
				$body .= self::_gen_part_file ( $file, $src_encoding, $dst_encoding, $dst_encoding_txt, $line_delimiter );
			}
			$body .= "--" . $mime_boundary . "--" . $line_delimiter . $line_delimiter;
		}
		
		$s .= $body;
		
		return $s;
	}
	
	static function send($sender, $mail, $to_list = false, $src_encoding = false, $dst = false) {
		#constant
		$line_delimiter = "\r\n";
		
		#parse dst
		if ($dst == "utf8" || $dst == "un") {
			$dst_encoding = "utf-8";
			$dst_encoding_txt = $dst_encoding;
		} elseif ($dst == "jp") {
			$dst_encoding = 'iso-2022-jp';
			$dst_encoding_txt = $dst_encoding;
		} elseif ($dst == "cn") {
			$dst_encoding = 'gbk';
			$dst_encoding_txt = 'gb2312';
		} else {
			$dst_encoding = 'gbk';
			$dst_encoding_txt = 'gb2312';
		}
		if (! $src_encoding) {
			$src_encoding = null;
		}
		
		#sender type
		$sender_type = "direct";
		
		if(!@$sender["type"] && @$sender["host"]){
			$sender_type="smtp";
		}
		
		$recipient_list = array ();
		if ($to_list === false) {
			foreach ( self::parse_email_from_header ( $mail, "to" ) as $email ) {
				$recipient_list [] = $email;
			}
			foreach ( self::parse_email_from_header ( $mail, "cc" ) as $email ) {
				$recipient_list [] = $email;
			}
			foreach ( self::parse_email_from_header ( $mail, "bcc" ) as $email ) {
				$recipient_list [] = $email;
			}
		} else {
			$mail = self::_replace_or_append_head ( $mail, "CC", false, $line_delimiter );
			$mail = self::_replace_or_append_head ( $mail, "BCC", false, $line_delimiter );
			
			$email_list = $to_list;
			if (is_string ( $email_list ) || (count ( $email_list ) == 2 && ! self::_is_email_name_pair_str ( $email_list [1] ))) {
				$email_list = array ($email_list );
			}
			$strs = array ();
			foreach ( $email_list as $email ) {
				$pair = self::_parse_email_name_pair ( $email );
				if (! $pair) {
					echo __CLASS__ . ": email not valid\n";
					continue;
				}
				$strs [] = self::_gen_email_name_pair ( $pair, $src_encoding, $dst_encoding, $dst_encoding_txt );
				$recipient_list [] = $pair [0];
			}
			if (count ( $strs ) > 0) {
				$mail = self::_replace_or_append_head ( $mail, "To", implode ( ",", $strs ), $line_delimiter );
			}
		}
		
		if (count ( $recipient_list ) == 0) {
			echo __CLASS__ . ": no recipient\n";
			return true;
		}
		
		#from
		$from = @$sender ["from"];
		//Util::dump($from);
		//echo "<pre>";
		//die ( __LINE__ . "@" . __FILE__ . ":" . print_r($from) );
		//echo "</pre>";
		if ($from) {
			$pair = self::_parse_email_name_pair ( $from );
			//Util::dump($pair);
			if (! $pair) {
				die ( __LINE__ . "@" . __FILE__ . ":" . "from cannot parse" );
			}
			$mail = self::_replace_or_append_head ( $mail, "From", self::_gen_email_name_pair ( $pair, $src_encoding, $dst_encoding, $dst_encoding_txt ),$line_delimiter );
			$from_email = $pair [0];
		} else {
			$from = self::parse_email_from_header ( $mail, "from" );
			if (count ( $from ) != 1) {
				$from_email = "info@mail.nikon-cp.com";
				$mail = self::_replace_or_append_head ( $mail, "From", $from_email,$line_delimiter);
			} else {
				$from_email = $from [0];
			}
		}
		//Util::dump($from_email);
		$sender ["from_email"] = $from_email;
		
		if ($sender_type == "direct") {
			return self::send_direct ( $sender, $mail, $recipient_list );
		}elseif($sender_type == "smtp"){
			//Util::dump($mail);
			$res = self::send_smtp ( $sender, $mail, $recipient_list );
			//echo "send_smtp res:{$res}<br>";
			return $res;
		}else {
			die ( __LINE__ . "@" . __FILE__ . ":" . "sender type not support yet" );
		}
	}
}
