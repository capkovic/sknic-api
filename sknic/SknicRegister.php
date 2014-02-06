<?php

namespace sknic;
use Exception;

require_once __DIR__ . '/SknicApi.php';

class SknicRegister extends SknicApi {
	
	protected $registerUrl = "main.jsp;jsessionid={SESSID}?form=domain_new_new";
	protected $registerUrlPost = "main.apply.jsp;jsessionid={SESSID}?form=domain_new";
	protected $registerUrlConfirm = "main.jsp;jsessionid={SESSID}?form=domain_new";
	
	public function registerDomain($domain, $handle = 'WEBS-0001', $defaultNameservers = true, $nameservers = array()){
		
		$tokens = $this->getToken(); // 1st attempt
		if (!isset($tokens['key']) || !isset($tokens['value'])) {
			$tokens = $this->getToken(); // 2nd attempt
		}
		if (!isset($tokens['key']) || !isset($tokens['value'])) {
			throw new SknicRegisterException('Error while getting token (or sknic login failed)');
		}
		
		$response = $this->postRequest($this->registerUrlPost, array(
			$tokens['key'] => $tokens['value'],
			'cmd' => '1',
			'dnstech' => $defaultNameservers ? '1' : '2',
			'domainName' => $domain,
			'ip1' => isset($nameservers['ip1']) ? $nameservers['ip1'] : '',
			'ip1v6' => isset($nameservers['ip1v6']) ? $nameservers['ip1v6'] : '',
			'ip2' => isset($nameservers['ip2']) ? $nameservers['ip2'] : '',
			'ip2v6' => isset($nameservers['ip2v6']) ? $nameservers['ip2v6'] : '',
			'ip3' => isset($nameservers['ip3']) ? $nameservers['ip3'] : '',
			'ip3v6' => isset($nameservers['ip3v6']) ? $nameservers['ip3v6'] : '',
			'ip4' => isset($nameservers['ip4']) ? $nameservers['ip4'] : '',
			'ip4v6' => isset($nameservers['ip4v6']) ? $nameservers['ip4v6'] : '',
			'nserver1' => isset($nameservers['nserver1']) ? $nameservers['nserver1'] : '',
			'nserver2' => isset($nameservers['nserver2']) ? $nameservers['nserver2'] : '',
			'nserver3' => isset($nameservers['nserver3']) ? $nameservers['nserver3'] : '',
			'nserver4' => isset($nameservers['nserver4']) ? $nameservers['nserver4'] : '',
			'ownerDomain' => $handle,
			'user_send' => 'Odosla» >>',
		));
		
		$responseTokens = $this->getConfirm();
		if ($responseTokens['msg_bad'] != '') {
			throw new SknicRegisterException($responseTokens['msg_bad']);
		}
		if ($responseTokens['key'] == '' ||  $responseTokens['value'] == '') {
			throw new SknicRegisterException('Wrong response tokens');
		}

		$response = $this->postRequest($this->registerUrlPost, array(
			$responseTokens['key'] => $responseTokens['value'],
			'cmd' => 2,
			'user_send' => 'Odosla» >>',
		));

		$confirm = $this->getConfirm();
		if($confirm['registered']) {
			return true;
		}
		throw new SknicRegisterException('Registration failed');
	}
	
	protected function getToken($reloadSessionIfTimeouted = true) {
		$response = $this->getRequest($this->registerUrl);
		
		if (!$response->isLogged()) {
			if (!$reloadSessionIfTimeouted) {
				return false;
			}
		
			if ($response->isSessionTimeouted()) {
				$this->startNewSession();
			}
			if (!$this->login()) {
				return false;
			}
			return $this->getToken(false);
		}
		
		preg_match('/<input type="hidden" name="(?P<key>\d+)" value="(?P<value>\w+)"\/>/', $response->body, $matches);
		return $matches;
	}
	
	protected function getConfirm(){
		
		$response = $this->getRequest($this->registerUrlConfirm);
		
		$body = $response->convertBody('iso-8859-2');
		preg_match('/<input type="hidden" name="(?P<key>\d+)" value="(?P<value>\w+)"\/>/', $body, $matches);
		preg_match('/<font class="msg_bad">(?P<msg_bad>(.|\s)*?)<\/font>/', $body, $matches2);
		preg_match('/<font class="msg_info">(?P<msg_info>(.|\s)*?)<\/font>/', $body, $matches3);
		preg_match('/<font class="msg_ok">(?P<msg_ok>(.|\s)*?)<\/font>/', $body, $matches4);
		return array(
			'key' => isset($matches['key']) ? $matches['key'] : '',
			'value' => isset($matches['value']) ? $matches['value'] : '',
			'msg_bad' => trim(isset($matches2['msg_bad']) ? $matches2['msg_bad'] : ''),
			'msg_info' => strip_tags(trim(isset($matches3['msg_info']) ? $matches3['msg_info'] : '')),
			'msg_ok' => strip_tags(trim(isset($matches4['msg_ok']) ? $matches4['msg_ok'] : '')),
			'registered' => mb_strpos($body, 'Doména bola zaregistrovaná') !== false ? true : false,
		);
	}
}

class SknicRegisterException extends SknicApiException {}