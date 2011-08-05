<?PHP
/*
 *  command : !uptime
 */

$iTime = time() - $this->_iStartTime;

$sMessage = "I've been hang out there for ".$iTime." second.";

$this->_sendMessage($sFrom, $sMessage);
?>