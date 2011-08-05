<?PHP
/*
 *  command : \ls
 */

$iMax = 5;

$oDB = new ZabberDB();
$sQuery = "SELECT "
	."id, content, length, datetime_c "
	."FROM msg "
	// ."WHERE itype = 0 "
	."ORDER BY datetime_c DESC "
	."LIMIT ".$iMax;
$oDB->query($sQuery);

$sOut = "the Last ".$iMax." Messages from All:\n"
	// ."more info at http://zabber/\n"
	."\n\n";
$oResult = $oDB->query($sQuery);

if (!$oResult) {
	$sOut .= "Error: Maybe Database is dead.";
	$this->_sendMessage($sFrom, $sOut);
}

$bEmpty = TRUE;
$aBigID = array();
$aOut = array();
while ($aRow = $oResult->fetch_assoc()) {
	$bEmpty = FALSE;
	$iID = array_shift($aRow);
	$aOut[$iID] = $aRow;
	if ($aRow["length"] > 50) {
		$aBigID[] = $iID;
	}
}

if (count($aBigID) > 0) {
	$sQuery = "SELECT id, content_ext "
		."FROM msg_content "
		."WHERE id IN (".implode(", ", $aBigID).")";
	$oResult = $oDB->query($sQuery);
	while ($aRow = $oResult->fetch_assoc()) {
		$aOut[$aRow["id"]]["content"] .= $aRow["content_ext"];
	}
}

foreach ($aOut as $aRow) {
	$sOut .= $aRow["datetime_c"]." : ".$aRow["content"]."\n\n";
}

if ($bEmpty) {
	$sOut .= "No message yet, you can type some";
}

$this->_sendMessage($sFrom, $sOut);
?>
