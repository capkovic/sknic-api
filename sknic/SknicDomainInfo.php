<?php

namespace sknic;
use Exception;

require_once __DIR__ . '/SknicApi.php';

class SknicDomainInfo extends SknicApi {
	
	protected $domain;
	protected $domainInfoUrl = "search.jsp;jsessionid={SESSID}?search_type=search_dom&search_for={DOM}";
	
	protected $owner;
	protected $registrar;
	protected $state;
	protected $lastStateChange;
	protected $expirationDate;

	public function setDomain($domain){
		$domainParts = explode('.', $domain);
		if(count($domainParts) == 2 && $domainParts[1] == 'sk'){
			$this->domain = $domain;
			return true;
		}
		throw new Exception("Wrong domain fromat");
	}

	public function getDomainName() {
		$domainParts = explode('.', $this->domain);
		return $domainParts[0];
	}
	
	public function loadDomainInfo($reloadSessionIfTimeouted = true)
	{
		
		$url = str_replace("{DOM}", $this->getDomainName(), str_replace("{SESSID}", $this->sessionStorage->get(), $this->domainInfoUrl));
		try {
			$response = new Response($this->httpConnection->get($url));
		} catch (\sknic\HttpRedirectException $error) {
			$response = new Response($error->getResponse());
		}
		
		if (!$response->isLogged()) {
			if (!$reloadSessionIfTimeouted) {
				return false;
			}
		
			if ($response->isSessionTimeouted()) {
				$this->startNewSession();
			}
			$this->login();
			return $this->loadDomainInfo(false);
		}
		
		$body = $response->convertBody('iso-8859-2');
		$responseStripped = strip_tags($body);
		$responseStripped = str_replace("&nbsp;", "", $responseStripped);

		//echo $responseStripped;
		if(strpos($responseStripped, "Doména je voľná") !== false){
			$this->state = 'FREE';
			return true;
		}
		
		preg_match("/Držiteľ domény\s+(?P<owner>\w+-\d+)/", $responseStripped, $matches);
		if(!isset($matches['owner'])){
			return false; // parse error
		}
		$this->owner = $matches['owner'];
		
		preg_match("/Registrátor\s+(?P<registrar>\w+-\d+)/", $responseStripped, $matches);
		if(isset($matches['registrar'])){
			$this->registrar = $matches['registrar'];
		}
		
		preg_match("/Stav domény\s+(?P<state>\w+)/", $responseStripped, $matches);
		if(!isset($matches['state'])){
			return false; // parse error
		}
		$this->state = $matches['state'];
		
		preg_match("/Posledna zmena stavu\s+(?P<lastStateChange>\d+.\d+.\d+)/", $responseStripped, $matches);
		if(isset($matches['lastStateChange'])){
			$this->lastStateChange = $matches['lastStateChange'];
		}
		
		
		preg_match("/Platna do\s+(?P<expirationDate>\d+.\d+.\d+)/", $responseStripped, $matches);
		if(isset($matches['expirationDate'])){
			$this->expirationDate = $matches['expirationDate'];
		}
		

		return true;
	}
	
	public function getOwner(){
		return $this->owner;
	}
	
	public function getRegistrar(){
		return $this->registrar;
	}
	
	public function getState(){
		return $this->state;
	}
	
	public function getLastStateChange(){
		return $this->lastStateChange;
	}
	
	public function getExpirationDate(){
		return $this->expirationDate;
	}

	public function getExpirationDateTimestamp()
	{
		$expiration = strtotime($this->expirationDate);
		if ($expiration === FALSE)
			return NULL;
		else
			return $expiration;
	}

	public function getLastStateChangeTimestamp()
	{
		$lastStateChange = strtotime($this->lastStateChange);
		if ($lastStateChange === FALSE)
			return NULL;
		else
			return $lastStateChange;
	}

}