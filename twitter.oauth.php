<?php
class TwitterOAuth {

	protected $_bDebug;

	protected $_sConsumerKey;
	protected $_sConsumerSecret;
	protected $_sToken;
	protected $_sTokenSecret;

	protected $_sCURLProxy;
	protected $_iCURLProxyType;

	function __construct($sConsumerKey, $sConsumerSecret) {
		$this->_sConsumerKey = $sConsumerKey;
		$this->_sConsumerSecret = $sConsumerSecret;
	}

	function setToken($sToken, $sTokenSecret) {
		$this->_sToken = $sToken;
		$this->_sTokenSecret = $sTokenSecret;
	}

	function setProxy($sProxy, $iType = CURLPROXY_SOCKS5) {
		$this->_sCURLProxy = $sProxy;
		$this->_iCURLProxyType = $iType;
	}

	function setDebug($bDebug = TRUE) {
		$this->_bDebug = !empty($bDebug);
	}

	function getBaseParam() {
		$aReturn = array(
			"oauth_consumer_key" => $this->_sConsumerKey,
			"oauth_nonce" => md5(microtime()),
			"oauth_signature_method" => "HMAC-SHA1",
			"oauth_timestamp" => time(),
			"oauth_version" => "1.0",
		);
		if ($this->_sToken) {
			$aReturn["oauth_token"] = $this->_sToken;
		}
		return $aReturn;
	}

	function send($aParam, $sURL, $sMethod = "GET") {

		$aParam += $this->getBaseParam();

		$aBaseString = array(
			$sMethod,
			$sURL,
			$this->buildQuery($aParam),
		);
		$sBaseString = implode("&", $this->urlEncodeRFC3986($aBaseString));

		$sSecret = $this->_sConsumerSecret."&";
		$sSecret .= $this->_sTokenSecret ?: "";

		$sSignature = hash_hmac('sha1', $sBaseString, $sSecret, true);

		$aParam["oauth_signature"] = base64_encode($sSignature);

		$sParam = $this->buildQuery($aParam);

		if ($this->_bDebug) {
			echo "\n";
			echo " >> [ Parameters to Send ]\n\n";
			print_r($aParam);
			echo "\n";
		}

		$hCURL = curl_init();
		curl_setopt_array($hCURL, array(
			CURLOPT_HEADER => $this->_bDebug,
			CURLOPT_RETURNTRANSFER => TRUE,
		));
		if ($this->_sCURLProxy) {
			curl_setopt_array($hCURL, array(
				CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
				CURLOPT_PROXY => $this->_sCURLProxy,
			));
		}

		if ($sMethod == "POST") {
			curl_setopt($hCURL, CURLOPT_POST, TRUE);
			curl_setopt($hCURL, CURLOPT_POSTFIELDS, $sParam);
		}

		curl_setopt($hCURL, CURLINFO_HEADER_OUT, $this->_bDebug);
		curl_setopt($hCURL, CURLOPT_URL,
			$sURL.($sMethod == "GET" ? "?".$sParam : ""));

		$sReturn = curl_exec($hCURL);

		if (!$this->_bDebug) {
			return $sReturn;
		}

		$aInfo = curl_getinfo($hCURL);

		$sReturn = str_replace(array("\r\n", "\r"), "\n", $sReturn);
		list($sReturnHeader, $sReturn) = explode("\n\n", $sReturn, 2);

		echo " >> [ Request Header ]\n\n";
		echo $aInfo["request_header"];
		unset($aInfo["request_header"]);

		if ($sMethod == "POST") {
			echo " >> [ POST Fields ]\n\n";
			echo $sParam;
			echo "\n\n";
		}

		echo " << [ Response Header ]\n\n";
		echo $sReturnHeader."\n\n";
		echo " << [ Response Content ]\n\n";
		echo $sReturn."\n\n";
		echo "    [ cURL Info ]\n\n";
		print_r($aInfo);
		echo "\n--- [ Debug Information END ] ---------------\n\n";

		return $sReturn;
	}

	function urlEncodeRFC3986($mInput) {
		if (is_array($mInput)) {
			return array_map(__METHOD__, $mInput);
		}
		$sReturn = $mInput;
		if (!is_scalar($sReturn)) {
			return '';
		}
		$sReturn = rawurlencode($sReturn);
		$sReturn = str_replace('%7E', '~', $sReturn);
		$sReturn = str_replace('+',   ' ', $sReturn);
		return $sReturn;
	}

	function buildQuery($aParam) {

		$aKey = $this->urlEncodeRFC3986(array_keys($aParam));
		$aValue = $this->urlEncodeRFC3986(array_values($aParam));
		$aParam = array_combine($aKey, $aValue);

		ksort($aParam, SORT_STRING);

		$aPair = array();
		foreach ($aParam as $sKey => $mValue) {
			if (is_array($mValue)) {
				natsort($mValue);
				foreach ($mValue as $sValue) {
					$aPair[] = $sKey.'='.$sValue;
				}
			} else {
				$aPair[] = $sKey.'='.$mValue;
			}
		}
		return implode('&', $aPair);
	}
}

