<?php
	namespace DesignStudioPlatform;	
	
	class API{
		
		const APIendPoint = 'https://designeditor-as-service.cloud/api/v2/',
		APIVERSION = 2,
		Connection_Error = 'Could not connect to endpoint',
		InvalidFile_Error = 'No valid file found',
		Json_Error = 'Unknown response. Could not process json',
		UnknownResponse_Error = 'Error. Process could not complete successfully',
		Onhold_Error = 'API endpoint not allowed at the moment',
		lockFile = 'ead73108ecac32024970a9ef1e90b34e.lock';
		private $ApiKey,
		$clientID,
		$basehost,
		$documentRoot;
		/* Accept App ID and Secret Key */
		public function __construct(string $id,string $key){
			
			$this->clientID = $id;
			$this->ApiKey = $key;
		}
		//Specify app/website root directory
		public function setDocumentRoot($value){
			if(!is_scalar($value))return false;
			if(!is_dir($value))return false;
			$this->documentRoot = $value;
			return $this;
		}
		//Specify app/website host
		public function setHost($value){
			if(!is_scalar($value))return false;
			if(!preg_match('#(?:(?:[a-z]*+)(?:\:\/\/)+)*([a-z0-9-_\.]+)#i',$value,$m)){
				return false;
			}
			$this->basehost = $m[1];
			return $this;
		}
		
		//Call the API endpoints
		/* Params 
				endpoint: @String API endpoint
				options: @Array Endpoint options
		*/
		public function call(string $endpoint,array $options=null){
			return $this->request(self::APIendPoint.$endpoint,$options);
		}
		//Prepare file for upload
		/* Accepts Indexed array or string  */
		public function FILES($fileList){
			$host = isset($this->basehost) ? $this->basehost:$_SERVER['HTTP_HOST'];
			$docRoot = isset($this->documentRoot) ? $this->documentRoot: $_SERVER['DOCUMENT_ROOT'];
			$files = $fileList;
			$str = is_string($files);
			if($str)$files = array($fileList);
			$list = array();
			$baseLen = strlen($docRoot);
			foreach($files as $i=>$e){
				if(!is_file($e))continue;
				$e = str_replace('\\','/',$e);
				$e = preg_replace('#/+#','/',$e);
				$e = substr($e,$baseLen);
				$e = 'http://'.$host.'/'.trim($e,'/');
				$list[] = $e;
			}
			if(empty($list))throw new \Exception(self::InvalidFile_Error);
			if($str)return $list[0];
			return $list;
		}
		
		private function request(string $url,array $data=null){
			try{
			if(self::isOnHold())throw new \exception(self::Onhold_Error);
			$headers = array(
			'apikey: '.$this->ApiKey,
			'clientID: '.$this->clientID,
			'Content-Type: application/x-www-form-urlencoded'
			);
			$timeout = 1000*30;
			$opt = array(
			CURLOPT_URL=>$url,
			CURLOPT_RETURNTRANSFER=>true,
			CURLOPT_AUTOREFERER=>true,
			CURLOPT_POST=>true,
			CURLOPT_HEADER=>true,
			CURLOPT_FOLLOWLOCATION=>true,
			CURLOPT_MAXREDIRS=>2,
			CURLOPT_CONNECTTIMEOUT_MS=>$timeout,
			CURLOPT_TIMEOUT_MS=>$timeout,
			CURLOPT_HTTPHEADER=>$headers,
			CURLOPT_USERAGENT=>__CLASS__.' '.self::APIVERSION,
			CURLOPT_SSL_VERIFYHOST=>0,
			CURLOPT_SSL_VERIFYPEER=>false
			);
			if(isset($data)){
				$in = http_build_query($data);
				$opt[CURLOPT_POSTFIELDS] = 	$in;
				$opt[CURLOPT_HTTPHEADER][] = 'Content-Length: '.strlen($in);
			}else{
			array_pop($opt[CURLOPT_HTTPHEADER]);
			}
			$http = curl_init();
			curl_setopt_array($http,$opt);
			$r = curl_exec($http);
			touch($_SERVER['TMP'].'/'.self::lockFile);
			if(!$r)throw new \Exception(self::Connection_Error);
			$status = curl_getinfo($http,CURLINFO_HTTP_CODE);
			$hz = curl_getinfo($http,CURLINFO_HEADER_SIZE);
			$headers = substr($r,0,$hz);
			$body = substr($r,$hz);
			$body = @json_decode($body,true);
			$headers = explode("\r\n",$headers);
			
			foreach($headers as $i=>$header){
				if(strpos($header,':')===false){
				unset($headers[$i]);
				continue;	
				}
				list($key, $value) = explode(':', $header, 2);
				unset($headers[$i]);
				if(stripos($key,'set-cookie')===0)continue;
				$value = trim($value);
				$headers[$key] = $value; 
			}
			if(!empty($headers) and is_array($headers) and array_key_exists('X-Ratelimit-Remaining',$headers)){
				file_put_contents($_SERVER['TMP'].'/'.self::lockFile,$headers['X-Ratelimit-Remaining'].'/'.$headers['X-Ratelimit-Limit']);
			}
			if(!$body)throw new \Exception(self::Json_Error);
			if($status !==200){
				$err = self::UnknownResponse_Error;
				if(isset($body['error']['message']))$err = $body['error']['message'];
				throw new \exception($err,$status);
			}
			
			curl_close($http);
			return (Object)array('body'=>$body['result'],'headers'=>$headers);
			}catch(Exception $e){
				if($http)curl_close($http);
				throw new \Exception($e->getMessage(),$this->getCode());
			}
		}
		private function isOnHold(){
			$file = $_SERVER['TMP'].'/'.self::lockFile;
			if(!is_file($file))return false;
			$tm = time();$ftm = filemtime($file);
			$on = strtotime('1 minute',$ftm) < $tm;
			if(!$on)return true;
			$s = file_get_contents($file);
			if(preg_match('/^(-?[0-9]+)\/(\d+)$/',$s,$m)){
				$free = strtotime('1 hour',$ftm) < $tm;
				if(($m[1] <= 0) and !$free)return true;
			}
			return false;
		}
	}
?>