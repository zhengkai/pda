<?PHP
/*
 *  command : \me
 */

$iMax = 5;

$oDB = new ZabberDB();
$sQuery = "SELECT "
	."id, content, UNIX_TIMESTAMP(date_c) as date_c "
	."FROM msg "
	."WHERE status != \"delete\" "
	."AND jid = ".$iJID." ORDER BY id DESC LIMIT ".$iMax;
$oDB->query($sQuery);

$sOut = "the Last ".$iMax." Messages from Yourself:\n\n";

if ($oResult = $oDB->query($sQuery)) {
	$bEmpty = TRUE;
	while ($aRow = $oResult->fetch_assoc()) {
		$bEmpty = FALSE;
		$sOut .= date("m-d H:i", $aRow["date_c"]).":\n".$aRow["content"]."\n\n";
	}
	if ($bEmpty) {
		$sOut .= "No message yet, you can type some";
	}
} else {
	$sOut .= "Error: Maybe Database is dead.";
}

$this->_sendMessage($sFrom, $sOut);
?>