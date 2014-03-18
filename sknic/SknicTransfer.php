<?php

namespace sknic;

require_once __DIR__ . '/SknicApi.php';

class SknicTransfer extends SknicApi {
	
	protected $getDomChangeRegUrl = "main.jsp;jsessionid={SESSID}?form=dom_ch_reg";
	protected $postDomChangeRegUrl = "main.apply.jsp;jsessionid={SESSID}?form=dom_ch_reg";
	protected $postDomChangeRegOkUrl = "main.apply.jsp;jsessionid={SESSID}?form=dom_ch_reg_ok";
	protected $getPrintUrl = "preview.jsp;jsessionid={SESSID}?id={PRINTID}";
	protected $checkSessionTimeout = true;
	
	public function change($domain) {
	
		if (strpos($domain, '.') !== false) {
			throw new SknicTransferException("invalide domain format, please remove tld part");
		}

		// load regId
		$response = $this->getRequest($this->getDomChangeRegUrl);
	
		if (!$response->isLogged()) {
			if (!$this->checkSessionTimeout) {
				throw new SknicLoginException('Sknic login failed');
			}
			if ($response->isSessionTimeouted()) {
				$this->startNewSession();
			}
			if (!$this->login()) {
				throw new SknicLoginException('Sknic login failed');
			}
			$this->checkSessionTimeout = false; // prevent login loop
			return $this->change($domain);
		}

		preg_match('/name="regId"\svalue="(?P<regId>[^"]*)"/', $response->convertBody('iso-8859-2'), $matches);
		$regId = trim(isset($matches['regId']) ? $matches['regId'] : null);

		if (!$regId) {
			throw new SknicLoginException('Failed to get regId.');
		}

		$response = $this->postRequest($this->postDomChangeRegUrl, array(
			'domain' => $domain,
			'regNic' => $this->username,
			'regId' => $regId,
			'user_send' => 'Odosla» >>',
		));

		$redirOk = strpos($response->headers, "dom_ch_reg_ok") !== false;

		if (!$redirOk) {
			$response = $this->getRequest($this->getDomChangeRegUrl);
			preg_match('/<font class="msg_bad">(?P<msg_bad>(.|\s)*?)<\/font>/', $response->convertBody('iso-8859-2'), $matches);
			$msg = trim(isset($matches['msg_bad']) ? $matches['msg_bad'] : '');
			throw new SknicTransferException("Domain error: ".$msg);
		}

		// reload to get results
		$response = $this->postRequest($this->postDomChangeRegOkUrl, array());
		$body = $response->convertBody('iso-8859-2');

		// find print ID
		preg_match_all("/displayPrint\(\'(?P<printId>\w+)\'\)/", $body, $matches);
		if(isset($matches['printId'][0]) && is_numeric($matches['printId'][0])){
			$printId = $matches['printId'][0];
			$response = $this->getRequest(str_replace("{PRINTID}", $printId, $this->getPrintUrl));
			$this->printHtml = $response->convertBody('iso-8859-2');
			if (strpos($this->printHtml, 'nie su instancie') !== false) {
				throw new SknicTransferException("Error from sknic: \"Chyba, nie su instancie.\"");
			}
		} else {
			throw new SknicTransferException("Failed to load print page");
		}
		
		return true;
	}
}

class SknicTransferException extends SknicApiException {}
