<?php

namespace sknic;
use Exception;

require_once __DIR__ . '/SknicApi.php';

class SknicNsChange extends SknicApi {
	
	
	protected $postDnsChangeUrl = "main.apply.jsp;jsessionid={SESSID}?form=dns_change";
	protected $getDnsChangeUrl = "main.jsp;jsessionid={SESSID}?form=dns_change";
	
	public function change($domain, $nameservers) {
		$existing = $this->getNsSettings($domain);
		
		$response = $this->postRequest($this->postDnsChangeUrl, array(
			'dnstech' => '2',
			'nserver1' => isset($nameservers['nserver1']) ? $nameservers['nserver1'] : '',
			'nserver2' => isset($nameservers['nserver2']) ? $nameservers['nserver2'] : '',
			'nserver3' => isset($nameservers['nserver3']) ? $nameservers['nserver3'] : '',
			'nserver4' => isset($nameservers['nserver4']) ? $nameservers['nserver4'] : '',
			'ip1' => isset($nameservers['ip1']) ? $nameservers['ip1'] : '',
			'ip1v6' => isset($nameservers['ip1v6']) ? $nameservers['ip1v6'] : '',
			'ip2' => isset($nameservers['ip2']) ? $nameservers['ip2'] : '',
			'ip2v6' => isset($nameservers['ip2v6']) ? $nameservers['ip2v6'] : '',
			'ip3' => isset($nameservers['ip3']) ? $nameservers['ip3'] : '',
			'ip3v6' => isset($nameservers['ip3v6']) ? $nameservers['ip3v6'] : '',
			'ip4' => isset($nameservers['ip4']) ? $nameservers['ip4'] : '',
			'ip4v6' => isset($nameservers['ip4v6']) ? $nameservers['ip4v6'] : '',
			'user_send' => 'Odosla» >>',
			'cmd' => '2',
		));
		
		// get result
		$response = $this->getRequest($this->getDnsChangeUrl);
		$body = $response->convertBody('iso-8859-2');
		preg_match('/<font class="msg_bad">(?P<msg_bad>(.|\s)*?)<\/font>/', $body, $matchesBad);
		if (isset($matchesBad['msg_bad'])) {
			throw new SknicNsChangeException(trim(strip_tags($matchesBad['msg_bad'])));
		}
		
		$response = $this->postRequest($this->postDnsChangeUrl, array(
			'user_send' => 'Odosla» >>',
			'cmd' => '3',
		));
		
		// get result
		$response = $this->getRequest($this->getDnsChangeUrl);
		
		$body = $response->convertBody('iso-8859-2');
		if (strpos($body, 'DNS záznamy boli zmenené') === false) {
			throw new SknicNsChangeException('Error while updating NS records.');
		}
		return true;
	}
	
	public function getNsSettings($domain, $reloadSessionIfTimeouted = true) {
		
		// test login
		$response = $this->getRequest($this->getDnsChangeUrl);
		if (!$response->isLogged()) {
			if (!$reloadSessionIfTimeouted) {
				throw new SknicLoginException('Sknic login failed');
			}
		
			if ($response->isSessionTimeouted()) {
				$this->startNewSession();
			}
			if (!$this->login()) {
				throw new SknicLoginException('Sknic login failed');
			}
			return $this->getNsSettings($domain, false);
		}
		
		// back to search list
		$response = $this->postRequest($this->postDnsChangeUrl, array(
			'user_back' => '<< Späť!',
			'cmd' => '2',
		));
		
		// search post
		$response = $this->postRequest($this->postDnsChangeUrl, array(
			'domainName' => $domain,
			'user_send' => 'Odosla» >>',
			'cmd' => '1',
		));
		
		// get result
		$response = $this->getRequest($this->getDnsChangeUrl);
		
		
		$body = $response->convertBody('iso-8859-2');
		
		if (strpos($body, 'Nie ste registrátorom tejto domény.') !== false) {
			throw new SknicNsChangeException('You are not registrator of this domain!');
		}
		if (strpos($body, 'Systémová chyba') !== false) {
			throw new SknicNsChangeException('Sknic system error!');
		}
		if (strpos($body, 'Neoprávnený prístup') !== false) {
			throw new SknicNsChangeException('Sknic system error!');
		}

		$nsSettings = array();
		foreach (array(
			'nserver1', 'nserver2', 'nserver3', 'nserver4',
			'ip1', 'ip2', 'ip3', 'ip4',
			'ip1v6', 'ip2v6', 'ip3v6', 'ip4v6',
		) as $key) {
			preg_match('/name="'.$key.'" value="(?P<value>[^"]*)"/Usi', $body, $matches);
			if (isset($matches['value'])) {
				$nsSettings[$key] = $matches['value'];
			}
		}
		if (preg_match('/name="dnstech"\s+value="1"\s+CHECKED/Usi', $body)) {
			$nsSettings['dnstech'] = 1;
		}
		if (preg_match('/name="dnstech"\s+value="2"\s+CHECKED/Usi', $body)) {
			$nsSettings['dnstech'] = 2;
		}
		
		if (empty($nsSettings)) {
			throw new SknicNsChangeException('Failed to get NS settings');
		}
		
		return $nsSettings;
	}
}

class SknicNsChangeException extends SknicApiException {}