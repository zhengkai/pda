<?PHP
/*
 *  command : *
 *
 *  send XML
 */

$sArgument = trim($sArgument);
if (empty($sArgument)) {
	$this->_sendMessage($sFrom, "What are you SHUOing");
	return FALSE;
}

$sTo = $sCommand;

if (empty($sTo)) {
	// to all

	$sContent = "";
	foreach ($this->_aRoster as $sRoster => $null) {
		// $this->_sendMessage($sFrom, $sHelp);
		// $sContent .= "[ ".$sRoster." ] ";
		$this->_sendMessage($sRoster, "\n".$sFrom." says:\n".$sArgument);
	}
	// $this->_sendMessage($sFrom, $sContent."\n\n".$sArgument);
} else {

	// to someone

	if (substr($sTo, -10) != "@gmail.com") {
		$sTo .= "@gmail.com";
	}

	$this->_sendMessage($sTo, "\n".$sFrom." says:\n".$sArgument);
}
?>