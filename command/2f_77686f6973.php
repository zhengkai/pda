<?PHP
/*
 *  command : \me
 */

$sDomain = $sArgument;

$sPattern = "/^[0-9a-z\\.\\-\\_]+$/";

if (preg_match($sPattern, $sDomain)) {
	exec("whois ".$sDomain, $aOut);
	$sOut = implode("\n", $aOut);
} else {
	$sOut = "Error Domain";
}

$this->_sendMessage($sFrom, $sOut);
?>