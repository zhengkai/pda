<?PHP

function getJID($sJID) {

	$oDB = new ZabberDB();

	$sJIDRow = "jid = \"".addslashes($sJID)."\"";

	$sQuery = "SELECT id FROM jid WHERE ".$sJIDRow;
	$oDB->query($sQuery);
	if (($oResult = $oDB->query($sQuery))&&($aRow = $oResult->fetch_assoc())) {
		return $aRow["id"];
	}

	$sQuery = "INSERT IGNORE INTO jid SET ".$sJIDRow;
	$oDB->query($sQuery);
	if ($oDB->insert_id > 0) {
		return $oDB->insert_id;
	}

	$sQuery = "SELECT id FROM jid WHERE ".$sJIDRow;
	$oDB->query($sQuery);
	if (($oResult = $oDB->query($sQuery))&&($aRow = $oResult->fetch_assoc())) {
		return $aRow["id"];
	}

	return FALSE;
}

function getJIDInt($iJID) {
	$iJID = intval($iJID);
	if ($iJID < 1) {
		return FALSE;
	}

	$oDB = new ZabberDB();

	$sQuery = "SELECT jid FROM jid WHERE id = ".$iJID;
	$oDB->query($sQuery);
	if (($oResult = $oDB->query($sQuery))&&($aRow = $oResult->fetch_assoc())) {
		return $aRow["jid"];
	}

	return FALSE;
}
?>