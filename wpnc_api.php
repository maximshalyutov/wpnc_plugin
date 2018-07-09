<?php

class DFX_API_Client
{
	var $curlObj;
	var $httpHeader;
	var $base_api_url;
	var $api_key = '';
	var $secret_key = '';
	var $server_request = false;
	var $error = '';
	var $headers = array();
	
	function __construct($base_api_url, $keys = array(), $server_request = false)
	{
		$this->setBaseAPIUrl($base_api_url);
		
		if ($server_request) {
			if (isset($keys['dfx_key'])) {
				$this->secret_key = $keys['dfx_key'];
				$this->server_request = true;
			}
			else {
				return new FX_Error('dfx_api_client', _('Please set DFX Key for server request'));
			}
		}
		else {
			$this->api_key = $keys['api_key'];
			$this->secret_key = $keys['secret_key'];
		}

		$this -> httpHeader[] = "Cache-Control: max-age=0";
		$this -> httpHeader[] = "Connection: keep-alive";
		$this -> httpHeader[] = "Keep-Alive: 300";
	}

	private function initCurlObject()
	{
		$this -> curlObj = curl_init();

		curl_setopt($this -> curlObj, CURLOPT_HEADER, 1);
		curl_setopt($this -> curlObj, CURLOPT_AUTOREFERER, 1);
		curl_setopt($this -> curlObj, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($this -> curlObj, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this -> curlObj, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($this -> curlObj, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($this -> curlObj, CURLOPT_CONNECTTIMEOUT, 120);
		curl_setopt($this -> curlObj, CURLINFO_HEADER_OUT, 1);

		if (defined('API_PORT')) {
			curl_setopt($this -> curlObj, CURLOPT_PORT, API_PORT);
		}
	}

	public function setBaseAPIUrl($baseUrl)
	{
		$baseUrl = str_replace('\\', '/', $baseUrl);

		//if (is_url($baseUrl)) {
			$baseUrl = trim($baseUrl, '/');
			$this -> base_api_url = preg_replace('/(\:[0-9]+)+/', '', $baseUrl);
/*		}
		else {
			$this->error = new FX_Error(__METHOD__, 'Invalid URL format');
			return $this->error;
		}*/
	}

	public function execRequest($endpoint, $httpMethod, $httpData = '', $encode = true, $return_raw = false)
	{
		if($endpoint[0] == '/') {
			$endpoint = substr($endpoint, 1);
		}		
		
		$endpoint = str_replace('\\', '/', $endpoint);

		if($endpoint[0] != '/') {
			$endpoint = '/'.$endpoint;
		}
		
		$httpURL = $this->base_api_url.$endpoint;

		$httpMethod = strtolower($httpMethod);

		if (!in_array($httpMethod, array('get', 'post', 'put', 'delete'))) {
			return new FX_Error(__METHOD__, 'Invalid HTTP method');
		}

		$this -> initCurlObject();

		curl_setopt($this -> curlObj, CURLOPT_HEADER, 1);

		if (!is_array($httpData)) {
			parse_str($httpData, $httpData);
		}

		//**************************************************************************
		
		$files = array();

		if ($encode === true) {
			if ($httpMethod == 'post') {
				foreach($httpData as $key => $value) {
					if ($value[0] == '@') {
						$files[$key] = $value;
					}
				}
			}
			$httpData = $this->_encrypt(json_encode($httpData));
			$httpData = array('data' => $httpData);
		}
		else {
			foreach($httpData as $key => $value) {
				$httpData[$key] = is_array($value) ? json_encode($value) : $value;
			}
		}
		
		if ($httpMethod != 'post') {
			$httpData = http_build_query($httpData);
		}

		//**************************************************************************
		$httpData = $this->_prepareData($httpData);

		if ($httpMethod == 'post') {
			$httpData = array_merge($httpData, $files);
		}

		//**************************************************************************
		switch ($httpMethod) {
			case 'get':
				$this -> _get($httpData, $httpURL);			
				break;
			case 'post':
				$this -> _post($httpData);
				break;
			case 'put':
				$this -> _put($httpData);
				break;
			case 'delete':
				$this -> _delete($httpData);
				break;
		}

		curl_setopt($this -> curlObj, CURLOPT_URL, $httpURL);

		$response = curl_exec($this -> curlObj);

		$info = curl_getinfo($this -> curlObj);
		
		//PARSE HEADERS
		//-----------------------------------------------------------------------
		
		$tmp_headers = substr($response, 0, $info['header_size']);

		if ($tmp_headers = explode("\r\n", $tmp_headers))
		{
			list ($server_protocol, $response_code, $response_status) = explode(' ', array_shift($tmp_headers));
			
			$this -> headers['server_protocol'] = $server_protocol;
			$this -> headers['status_code'] = $response_code.' '.$response_status;
			
			foreach ($tmp_headers as $item) {
				$item = trim($item);
				if ($item) {
					list ($key, $value) = explode(':', $item);
					$this -> headers[trim($key)] = trim($value);
				}
			}
			
			if ($this -> headers['FlexiDB-Request-Encryption'] == 'disabled') {
				$encode = false;
			}
		}

		//-----------------------------------------------------------------------		
		
		$raw_result = substr($response, $info['header_size']);

		if ($return_raw) {
			return $raw_result;
		}

		$http_codes = array(
			100 => 'Continue',
			101 => 'Switching Protocols',
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => '(Unused)',
			307 => 'Temporary Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported'
		);

		if ($info['http_code'] !== 200) {
			if (array_key_exists($info['http_code'], $http_codes)) {
				$errors = json_decode($raw_result, true);
				$error = new FX_Error('http_code', '<b>'.$http_codes[$info['http_code']].'</b>');
				
				if (isset($errors['errors'])) {
					$errors = $errors['errors'];
					foreach($errors as $err) {
						$error -> add('http_code', $err[0]);
					}
				}
			}
			else {
				$error = new FX_Error('http_code', _('Unable to get response from server'));
			}
			return $error;
		}

		$result = '';

		$decoded = json_decode($raw_result, true);
		
		if (isset($decoded['errors'])) {
			foreach ($decoded['errors'] as $key=>$value) {
				$result = new FX_Error('test', $value[0]);
			}
		}

		if (is_fx_error($result)) {
			return $result;
		}

		if ($curl_error = curl_error($this -> curlObj)) {
			add_log_message('curl_error', $curl_error);
			return new FX_Error('curl_error', $curl_error);
			die();
		}

		if ($raw_result === NULL || $raw_result === '') {
			return new FX_Error(__METHOD__, _('Empty result'));
		}

		//echo $raw_result;

		if ($encode === true) {
			$raw_result = $this->_decrypt($raw_result);
		}

		$result = json_decode($raw_result, true);

		if (is_array($result) && array_key_exists('errors', $result)) {
			return $this -> _convert_result_to_fx_error($result);
		}

		if ($result === NULL) {
			return new FX_Error(__METHOD__, _('Unable to decode JSON data'));
		}
		
		return $result;
	}

	public function setAcceptType($type)
	{
		// xml  -> text/xml
		// html -> text/html
		// json -> application/json
		// text -> text/plain
		// Else -> whatever was there

		if(is_array($type)) {
			foreach($type as $k => $v) {
				$v = strtolower($v);
				if($v == "xml")
					$type[$k] = "text/xml";
				elseif($v == "html")
					$type[$k] = "text/html";
				elseif($v == "json")
					$type[$k] = "application/json";
				elseif($v == "text")
					$type[$k] = "text/plain";
			}
			$type = implode(",", $type);
		}
		
		$this -> httpHeader[] = "Accept: ".$type;
	}
	
	private function _prepareData($data)
	{
		if (!$this->server_request) {
			if (is_array($data)) {
				$data['api_key'] = $this->api_key;
			}
			else {
				$data = 'api_key='.$this->api_key.($data ? '&' : '').$data;
			}
		}
	
		return $data;
	}

	private function _get($data = NULL, &$url)
	{
		curl_setopt($this -> curlObj, CURLOPT_HTTPGET, true);

		if($data != NULL) {
			if(is_array($data)) {

				$data = http_build_query($data, 'arg');
			}
			else {
				parse_str($data, $tmp);
				$data = "";
				$first = true;
				foreach($tmp as $k => $v) {
					if(!$first) {
						$data .= "&";
					}
					$data .= $k . "=" . urlencode($v);
					$first = false;
				}
			}
			
			$url .= "?".$data;
		}
	}

	private function _post($data = NULL)
	{
		$data['dfx_key'] = $this -> dfx_key;
		curl_setopt($this -> curlObj, CURLOPT_POST, true);
		curl_setopt($this -> curlObj, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($this -> curlObj, CURLOPT_POSTFIELDS, $data);
	}

	private function _put($data = NULL)
	{
		if ($data) {$data .='&dfx_key='.$this -> dfx_key;}
		curl_setopt($this -> curlObj, CURLOPT_PUT, true);
		$resource = fopen('php://temp', 'rw');
		$bytes = fwrite($resource, $data);
		rewind($resource);

		if($bytes !== false) {
			curl_setopt($this -> curlObj, CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: PUT'));
			curl_setopt($this -> curlObj, CURLOPT_INFILE, $resource);
			curl_setopt($this -> curlObj, CURLOPT_INFILESIZE, $bytes);
		}
		else {
			throw new Exception('Could not write PUT data to php://temp');
		}
	}

	private function _delete($data = null)
	{
		if ($data) {$data .='&dfx_key='.$this -> dfx_key;}
		curl_setopt($this -> curlObj, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($this -> curlObj, CURLOPT_PUT, true);

		if($data != null) {
			$resource = fopen('php://temp', 'rw');
			$bytes = fwrite($resource, $data);
			rewind($resource);

			if($bytes !== false) {
				curl_setopt($this -> curlObj, CURLOPT_INFILE, $resource);
				curl_setopt($this -> curlObj, CURLOPT_INFILESIZE, $bytes);
			}
			else {
				throw new Exception('Could not write DELETE data to php://temp');
			}
		}

	}
	
	private function _convert_result_to_fx_error($result = array())
	{
		$errors = new FX_Error();

		foreach ($result['errors'] as $code => $messages) {
			for($i = 0; $i < count($messages); $i++) {
				$errors -> add($code, $messages[$i]);
			}
		}

		return $errors;
	}

	private function _decrypt($data)
	{
		if (!is_fx_error($data) && ctype_xdigit($data)) {
			$data = pack("H*" , $data);
			return Blowfish::decrypt($data, $this->secret_key, Blowfish::BLOWFISH_MODE_EBC, Blowfish::BLOWFISH_PADDING_RFC);
		}
		else {
			if (is_fx_error($data)) {
				fx_print($data->get_error_message());
			}
		}
	}

	private function _encrypt($data)
	{
		return bin2hex(Blowfish::encrypt($data, $this->secret_key, Blowfish::BLOWFISH_MODE_EBC, Blowfish::BLOWFISH_PADDING_RFC));
	}
}