<?php

namespace sknic;
use Exception;

require_once __DIR__ . '/SknicApi.php';

class SknicPay extends SknicApi {
	
	
	protected $paySearchUrl = "main.apply.jsp;jsessionid={SESSID}?form=domain_cash_list_search";
	protected $paySearchRetrieveUrl = "main.jsp;jsessionid={SESSID}?form=domain_cash_list&action=retrieve";
	protected $paySelectedDomainUrl = "main.apply.jsp;jsessionid={SESSID}?form=domain_cash_list";
	protected $domainCashListUrl = "main.jsp;jsessionid={SESSID}?form=domain_cash_list";
	
	
	public function payDomain($domain) {
		$token = $this->searchDomain($domain);
		
		// payment summary post
		try {
			$url = str_replace("{SESSID}", $this->sessionStorage->get(), $this->paySelectedDomainUrl);
			$response = new Response($this->httpConnection->post($url, array(
				'pay' => $token,
				'user_send' => 'Odosla» >>',
				'cmd' => '2',
			)));
		} catch (\sknic\HttpRedirectException $error) {
			$response = new Response($error->getResponse());
		}
		
		
		// payment summary list
		try {
			$url = str_replace("{SESSID}", $this->sessionStorage->get(), $this->domainCashListUrl);
			$response = new Response($this->httpConnection->get($url));
		} catch (\sknic\HttpRedirectException $error) {
			$response = new Response($error->getResponse());
		}
		
		// test if domain name is correct
		$body = $response->convertBody('iso-8859-2');
		if (!preg_match("/<td[^>]*>$domain<\/td>/Usi", $body)) {
			throw new SknicPayException('Domain name check failed');
		}
		
		// make final payment
		try {
			$url = str_replace("{SESSID}", $this->sessionStorage->get(), $this->paySelectedDomainUrl);
			$response = new Response($this->httpConnection->post($url, array(
				'pay' => $token,
				'user_send' => 'Odosla» >>',
				'cmd' => '3',
			)));
		} catch (\sknic\HttpRedirectException $error) {
			$response = new Response($error->getResponse());
		}
		
		// load confirmation
		try {
			$url = str_replace("{SESSID}", $this->sessionStorage->get(), $this->domainCashListUrl);
			$response = new Response($this->httpConnection->get($url));
		} catch (\sknic\HttpRedirectException $error) {
			$response = new Response($error->getResponse());
		}
		
		// test if domain is payed
		$body = $response->convertBody('iso-8859-2');
		if (strpos($body, 'Domény boli uložené na zbernú faktúru.') === false) {
			throw new SknicPayException('Domain payment failed in confirmation screen');
		}
		
		return true;
	}

	public function searchDomain($domain, $reloadSessionIfTimeouted = true) {
		
		// test login
		$response = $this->getRequest($this->domainCashListUrl);
		if (!$response->isLogged()) {
			if (!$reloadSessionIfTimeouted) {
				throw new SknicPayException('Sknic login failed');
			}
		
			if ($response->isSessionTimeouted()) {
				$this->startNewSession();
			}
			if (!$this->login()) {
				throw new SknicPayException('Sknic login failed');
			}
			return $this->searchDomain($domain, false);
		}
		
		
		// back to search list
		$response = $this->postRequest($this->paySelectedDomainUrl, array(
			'user_back' => '<< Späť!',
			'cmd' => '3',
		));
		
		// search domain
		$response = $this->postRequest($this->paySearchUrl, array(
			'domain' => $domain,
			'nic' => '',
			'company' => '',
			'status' => 'ALL',
			'pagelimit' => '50',
			'datumPlatDoStart' => 'ALL',
			'datumPlatDoEnd' => 'ALL',
			'reg_read' => '',
			'sortBy' => 'dom_name',
			'user_back' => 'Odosla» >>',
		));
		
		// retrieve search list
		$response = $this->getRequest($this->paySearchRetrieveUrl);
		
		
		// parse pay key
		$body = $response->convertBody('iso-8859-2');
		preg_match('/<input type="checkbox" name="pay" value="(?P<value>\w+)"/', $body, $matches);
		if (isset($matches['value'])) {
			return $matches['value'];
		}
		throw new SknicPayException('Domain is already payed or is not in sknic domain to pay list');
	}
}

class SknicPayException extends SknicApiException {}