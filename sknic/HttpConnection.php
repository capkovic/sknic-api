<?php

namespace sknic;
use Exception;

class HttpConnection {

	public $ssl = false;
	protected $caFile;
	protected $url;
	protected $publicKey;
	protected $privateKey;
	protected $persistent;
	protected $connection;
	protected $header;

	public function __construct($url, $publicKey = null, $privateKey = null) {
		$this->url = $url;
		$this->publicKey = $publicKey;
		$this->privateKey = $privateKey;
	}

	public function get($path) {
		$response = $this->doRequest('GET', $path);
		if ($response->responseCode === 200) {
			return $response;
		}
		$this->throwExceptionByStatus($response);
	}

	public function post($path, $params = null) {
		$response = $this->doRequest('POST', $path, http_build_query($params));
		if ($response->responseCode === 200 || $response->responseCode === 201) {
			return $response;
		}
		$this->throwExceptionByStatus($response);
	}

	public function put($path, $params = null) {
		$response = $this->doRequest('PUT', $path, http_build_query($params));
		if ($response->responseCode === 200 || $response->responseCode === 201) {
			return $response;
		}
		$this->throwExceptionByStatus($response);
	}

	public function delete($path) {
		$response = $this->doRequest('DELETE', $path);
		if ($response->responseCode === 200) {
			return $response;
		}
		$this->throwExceptionByStatus($response);
	}

	protected function doRequest($httpVerb, $path, $requestBody = null) {
		if (strpos($path, $this->url) === 0) {
			$path = substr($path, strlen($this->url) - 1);
		}

		$curl = $this->getConnection();
		curl_setopt($curl, CURLOPT_TIMEOUT, 60);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $httpVerb);
		curl_setopt($curl, CURLOPT_URL, $this->url . $path);
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Accept: text/html',
			'Content-Type: application/x-www-form-urlencoded',
			'User-Agent: PHP',
		));

		if ($this->publicKey !== null && $this->privateKey !== null) {
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($curl, CURLOPT_USERPWD, $this->publicKey . ':' . $this->privateKey);
		}


		if ($this->ssl) {
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
			if ($this->caFile)
				curl_setopt($curl, CURLOPT_CAINFO, $this->caFile);
		} else {
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		}

		if (!empty($requestBody)) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);
		}

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		
		$response = curl_exec($curl);
		$httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		if ($httpStatus == 0) {
			$errorMessage = curl_error($curl);
			throw new HttpException($errorMessage);
		}

		if (!$this->persistent) {
			curl_close($curl);
		}
		
		$header = substr($response, 0, $headerSize);
		$body = substr($response, $headerSize);

		return new HttpResult($body, $header, $httpStatus);
	}
	
	protected function getConnection() {
		if ($this->persistent) {
			if ($this->connection === null) {
				$this->connection = $curl = curl_init();
			} else {
				$curl = $this->connection;
			}
		} else {
			$curl = curl_init();
		}
		return $curl;
	}

	protected function throwExceptionByStatus($response, $msg = null) {
		$status = $response->responseCode;
		if ($status == 302) {
			throw new HttpRedirectException($msg, $response);
		} elseif ($status == 401) {
			throw new HttpAuthenticationException($msg, $response);
		} elseif ($status == 403) {
			throw new HttpAccessDeniedException($msg, $response);
		} elseif ($status == 404) {
			throw new HttpNotFoundException($msg, $response);
		} elseif ($status == 500) {
			throw new HttpServerErrorException($msg, $response);
		} elseif ($status == 501) {
			throw new HttpNotImplementedException($msg, $response);
		} else {
			throw new HttpException($msg, $response);
		}
	}

	public function setCaFile($f) {
		$this->caFile = $f;
		$this->ssl = true;
	}

	public function setPersistentConnection($v) {
		$this->persistent = (bool) $v;
	}
}

class HttpResult {
	
	public $responseCode;
	public $headers;
	public $body;

	public function __construct($body, $headers, $responseCode) {
		$this->body = $body;
		$this->headers = $headers;
		$this->responseCode = $responseCode;
	}
}

class HttpException extends Exception {

	protected $response;

	public function __construct($message, $response='') {
		$this->response = $response;
		parent::__construct($message);
	}
	
	public function getResponse() {
		return $this->response;
	}

}
class HttpAccessDeniedException extends HttpException {}
class HttpAuthenticationException extends HttpException {}
class HttpServerErrorException extends HttpException {}
class HttpNotImplementedException extends HttpException {}
class HttpNotFoundException extends HttpException {}
class HttpJsonParseException extends HttpException {}
class HttpRedirectException extends HttpException {}
