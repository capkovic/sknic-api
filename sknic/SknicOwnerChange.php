<?php

namespace sknic;

require_once __DIR__ . '/SknicApi.php';

class SknicOwnerChange extends SknicApi {
	
	protected $getDomChangeUserUrl = "main.jsp;jsessionid={SESSID}?form=dom_ch_user";
	protected $postDomChangeUserUrl = "main.apply.jsp;jsessionid={SESSID}?form=dom_ch_user";
	protected $postDomChangeUserOkUrl = "main.apply.jsp;jsessionid={SESSID}?form=dom_ch_user_ok";
	protected $getDomainInfoPrintUrl = "preview.jsp;jsessionid={SESSID}?id={PRINTID}";
	protected $checkSessionTimeout = true;
	public $wkhtmltopdfPath = "wkhtmltopdf";
	
	public function change($domain, $newOwner) {
	
		if (strpos($domain, '.') !== false) {
			throw new SknicOwnerChangeException("invalide domain format, please remove tld part");
		}
	
		$response = $this->postRequest($this->postDomChangeUserUrl, array(
			'domain_name' => $domain,
			'new_user_nic' => $newOwner,
			'user_send' => 'Odosla» >>',
		));

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
			return $this->change($domain, $newOwner);
		}

		$redirOk = strpos($response->headers, "dom_ch_user_ok") !== false;

		if (!$redirOk) {
			$response = $this->getRequest($this->getDomChangeUserUrl);
			preg_match('/<font class="msg_bad">(?P<msg_bad>(.|\s)*?)<\/font>/', $response->convertBody('iso-8859-2'), $matches);
			$msg = trim(isset($matches['msg_bad']) ? $matches['msg_bad'] : '');
			throw new SknicOwnerChangeException("Domain error: ".$msg);
		}

		// reload to get results
		$response = $this->postRequest($this->postDomChangeUserOkUrl, array());
		$body = $response->convertBody('iso-8859-2');

		// find print ID
		preg_match_all("/displayPrint\(\'(?P<printId>\w+)\'\)/", $body, $matches);
		if(isset($matches['printId'][0]) && is_numeric($matches['printId'][0])){
			$printId = $matches['printId'][0];
			$response = $this->getRequest(str_replace("{PRINTID}", $printId, $this->getDomainInfoPrintUrl));
			$this->printHtml = $response->convertBody('iso-8859-2');
			if (strpos($this->printHtml, 'nie su instancie') !== false) {
				throw new SknicOwnerChangeException("Error from sknic: \"Chyba, nie su instancie.\"");
			}
		} else {
			throw new SknicOwnerChangeException("Failed to load print page");
		}
		
		return true;
	}
	
	public function getPdf(){
		if($this->printHtml === ''){
			throw new SknicOwnerChangeException("Print HTML not loaded!");
		}
		$rand = rand();
		$html = $this->printHtml;
		$html = str_replace("styles/nic.css", "https://www.sk-nic.sk/styles/nic.css", $html);
		$html = str_replace("charset=iso-8859-2", "charset=utf-8", $html);
		$html = str_replace("<img src=\"", "<img src=\"https://www.sk-nic.sk/", $html);

		$pathToHtml = "/tmp/sknic_owner_$rand.html";
		$pathToPdf = "/tmp/sknic_owner_$rand.pdf";

		file_put_contents($pathToHtml, $html);
		$wkhtmlToPdfOutput = "";
		exec($this->wkhtmltopdfPath." --encoding UTF8 $pathToHtml $pathToPdf 2>&1", $wkhtmlToPdfOutput);
		unlink($pathToHtml);

		if (file_exists($pathToPdf)) {
			$pdf = file_get_contents($pathToPdf);
			unlink($pathToPdf);
		} else {
			throw new SknicOwnerChangeException("wkhtmltopdf failed with error: ".$wkhtmlToPdfOutput);
		}
		return $pdf;
	}
}

class SknicOwnerChangeException extends SknicApiException {}
