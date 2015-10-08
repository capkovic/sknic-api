<?php

namespace sknic;
use Exception;

require_once __DIR__ . '/HttpConnection.php';
require_once __DIR__ . '/SessionStorage.php';


class Response {

	public $headers;
	public $body;
	public $responseCode;


	public function __construct(\sknic\HttpResult $r) {
		$this->headers = $r->headers;
		$this->body = $r->body;
		$this->responseCode = $r->responseCode;
	}
	
	public function isSessionTimeouted()
	{
		if($this->body === null){
			return null;
		}
		if(strpos($this->body, "Chyba: spojenie ukon") !== false){
			return true;
		}
		
		if(strpos($this->headers, "Location: https://www.sk-nic.sk/session.end.jsp") === false){
			return false;
		}
		
		return true;
	}
	
	public function isLogged()
	{
		if($this->body === null){
			return null;
		}

		if(strpos($this->body, '<INPUT TYPE="PASSWORD" CLASS="input" NAME="user_pass"') !== false) {
			return false;
		}
		
		if($this->isSessionTimeouted()){
			return false;
		}
		
		if(strpos($this->body, "prihlásený ako") === false){
			return true;
		}
		return false;
	}
	
	public function convertBody($convertFromFormating = null)
	{
		if ($convertFromFormating !== null) {
			return iconv($convertFromFormating, 'UTF-8', $this->body);
		} else {
			return $this->body;
		}
	}
}

class SknicApi {
	
	protected $httpConnection;
	protected $sessionStorage;
	protected $sessionStoragePath = "/tmp/sknic_sessions";
	protected $sknicBasePath = "https://www.sk-nic.sk/";
	protected $indexUrl = "index.jsp";
	protected $loginUrl = "main.apply.jsp;jsessionid={SESSID}?form=login";
	protected $username;
	protected $password;
	protected $lockTimeout = 60;
	public $wkhtmltopdfPath = "wkhtmltopdf";
	public $printHtml;

	public function __construct($storage = null, $sknicBasePath=null)
	{
		$url = $sknicBasePath!==null ? $sknicBasePath : $this->sknicBasePath;
		$this->httpConnection = new \sknic\HttpConnection($url);
		$this->httpConnection->ssl = true;
		$this->httpConnection->setPersistentConnection(true);
		
		if ($storage instanceof ISessionStorage) {
			$this->sessionStorage = $storage;
		} else {
			$this->sessionStorage = new FileSessionStorage();
			$this->sessionStorage->setFilePath($this->sessionStoragePath);
		}
	}
	
	public function setSessionStorage(ISessionStorage $storage) {
		$this->sessionStorage = $storage;
	}

	public function startNewSession()
	{
		$response = $this->getRequest($this->indexUrl);
		
		preg_match_all("/Location: https:\/\/www.sk-nic.sk\/main.jsp;jsessionid=(?P<sessionid>\w+)/", $response->headers, $matches);
		$sessionId = isset($matches['sessionid'][0]) ? $matches['sessionid'][0] : null;
		if($sessionId){
			$this->sessionStorage->set($sessionId);
			return $response;
		}
		return false;
	}


	public function login($reloadSessionIfTimeouted = true)
	{
		if ($this->password == '') {
			throw new SknicLoginException('Password for sknic api is empty!');
		}
		
		$this->waitForLock();
		
		$response = $this->postRequest($this->loginUrl, array(
			'user_nic' => $this->username,
			'user_pass' => $this->password,
		));

		if ($response->isSessionTimeouted()) {
			if (!$reloadSessionIfTimeouted) {
				return false;
			}
			$this->startNewSession();
			return $this->login(false);
		}

		$data = $response->convertBody('iso-8859-2');
		if (strpos($data, "Uvedený registrátorský NIC neexistuje.") !== false) {
			return false;
		}
		if (strpos($data, "Zlé heslo do systému.") !== false) {
			return false;
		}
		if (strpos($data, "Váš login je dočasne zablokovaný") !== false) {
			return false;
		}
		
		if ($response->isLogged()) {
			return true;
		}
	}

	public function setUsername($v)
	{
		$this->username = $v;
	}
	
	public function setPassword($v)
	{
		$this->password = $v;
	}
	
	public function setLockTimeout($v) {
		$this->lockTimeout = (int) $v;
	}

	public function getRequest($url) {
		try {
			return new Response($this->httpConnection->get(str_replace("{SESSID}", $this->sessionStorage->get(), $url)));
		} catch (\sknic\HttpRedirectException $error) {
			return new Response($error->getResponse());
		}
	}
	
	public function postRequest($url, $data) {
		try {
			return new Response($this->httpConnection->post(str_replace("{SESSID}", $this->sessionStorage->get(), $url), $data));
		} catch (\sknic\HttpRedirectException $error) {
			return new Response($error->getResponse());
		}
	}
	
	public function waitForLock() {
		$i = 0;
		while ($i < $this->lockTimeout) {
			if ($this->sessionStorage->lock()) {
				return true;
			} else {
				usleep(50000); // 50ms
				$i+=0.05;
			}
		}
		throw new SknicApiLockTimeoutException('Sknic timeout');
	}
	
	public function unlock() {
		$this->sessionStorage->unlock();
	}
	
	public function getPdf() {
		if($this->printHtml === ''){
			throw new SknicTransferException("Print HTML not loaded!");
		}
		$rand = rand();
		$html = $this->printHtml;
		$html = str_replace("styles/nic.css", "https://www.sk-nic.sk/styles/nic.css", $html);
		$html = str_replace("charset=iso-8859-2", "charset=utf-8", $html);
		$html = str_replace("<img src=\"", "<img src=\"https://www.sk-nic.sk/", $html);

		$pathToHtml = "/tmp/sknic_owner_$rand.html";
		$pathToPdf = "/tmp/sknic_owner_$rand.pdf";

		file_put_contents($pathToHtml, $html);
		$wkhtmlToPdfOutput = array();
		exec($this->wkhtmltopdfPath." --encoding UTF8 $pathToHtml $pathToPdf 2>&1", $wkhtmlToPdfOutput);
		unlink($pathToHtml);

		if (file_exists($pathToPdf)) {
			$pdf = file_get_contents($pathToPdf);
			unlink($pathToPdf);
		} else {
			throw new SknicTransferException("wkhtmltopdf failed with error: ".implode(" ", $wkhtmlToPdfOutput));
		}
		return $pdf;
	}
}

class SknicApiException extends Exception {}
class SknicApiLockTimeoutException extends SknicApiException {}
class SknicLoginException extends SknicApiException {}