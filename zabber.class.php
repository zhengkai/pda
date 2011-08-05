<?PHP
require_once("xmlize.inc.php");

class Zabber {

	public $sHost = "localhost";
	public $iPort = 5222;

	public $sHostTo = "";

	public $iTimeout = 20;

	public $sStatus = "Zabber - a PHP Jabber Class";

	public $sUser     = "";
	public $sPassword = "";

	public $sResource = "Zabber";
	public $sJID      = "";
	public $bDebug    = TRUE;

	protected $_hStream    = null;
	protected $_hLogWork   = null;
	protected $_hLogPocket = null;
	protected $_iStreamID  = null;

	protected $_iUniqueID = 0;

	protected $_aPresenceType = array("unavailable", "subscribe", "subscribed", "unsubscribe", "unsubscribed", "probe", "error");

	protected $_aXMLChar    = array("&", "<", ">", "]");
	protected $_aXMLCharOut = array();
	protected $_iStartTime  = 0;

	protected $_bAuth = FALSE;
	protected $_aRoster = array();

	public function __construct() {
		$this->_log("\n\n\n\nClass Begin\n\n");
	}

	// 主运行
	public function run() {
		$this->_iStartTime = time();

		while (TRUE) {
			while (!$this->_connect()) {
				sleep(1);
			}
			while ($this->_isConnected()) {
				sleep(1);
				$aData = $this->_receive();
				if (empty($aData)) {
					continue;
				}
				foreach ($aData as $sAction => $aCollection) {
					foreach ($aCollection as $aRow) {
						switch ($sAction) {
							case "stream:stream":
								$this->_parseStream($aRow);
								break;
							case "presence":
								$this->_parsePresence($aRow);
								break;
							case "message":
								$this->_parseMessage($aRow);
								break;
							case "iq":
								$this->_parseIq($aRow);
								break;
							case "proceed":
								$this->_parseProceed($aRow);
								break;
							case "success":
								$this->_parseSuccess($aRow);
								break;
							case "failure":
								$this->_parseFailure($aRow);
							default:
								// unparsed pocket, maybe you need to record and parse it
								break;
						}
					}
				}
			}
			$this->_eventLostConnect();
			sleep(5);
		}
	}

	/*
	 *  parse methods
	 *
	 *  read and parse any received pocket,
	 *  named by begin element name, "<stream:stream", "<iq", etc
	 *  then throw it to a _event method, or do other actions
	 */

	protected function _parseStream($aData) {
		if ( // $aData['@']['from'] == $this->sHostTo &&
			$aData['@']['xmlns'] != "jabber:client"
			|| $aData['@']["xmlns:stream"] != "http://etherx.jabber.org/streams")
		{
			$this->_log("Unrecognized stream packet");
		}

		if (empty($this->sHostTo)) {
			$this->sHostTo = $aData['@']['from'];
		}
		$this->_iStreamID = $aData['@']['id'];
		$this->_eventConnected();

		$aDataStreamFeatures =& $aData["#"]["stream:features"][0];

		if (isset($aData["#"]["stream:error"])) {
			$aError = $aData["#"]["stream:error"][0]["#"];
			if (isset($aError["host-unknown"])) {
				if ($this->sHostTo == $this->sHost) {
					$aTemp = explode("@", $this->sUser, 2);
					if (!empty($aTemp[1])) {
						$sHostTo = trim($aTemp[1]);
					}
					if (!empty($sHostTo)&&($this->sHostTo != $sHostTo)) {
						fclose($this->_hStream);
						$this->_hStream = null;
						$this->sHostTo = $sHostTo;
						$this->_connect();
					} else {
						$this->_log("Stream host \"".$this->sHost."\" is not accpeted, define a right \$this->sHostTo");
					}
				} else {
					$this->_log("Stream host \"".$this->sHost."\" and \"".$this->sHostTo."\" are not accpeted, define a right \$this->sHostTo");
				}
			}
			return FALSE;
		}

		if (isset($aDataStreamFeatures["#"]["starttls"])&&($aDataStreamFeatures["#"]["starttls"][0]["@"]["xmlns"] == "urn:ietf:params:xml:ns:xmpp-tls")) {

			// TLS Connect
			$this->_log("Start TLS Connect");
			$this->_send("<starttls xmlns=\"urn:ietf:params:xml:ns:xmpp-tls\" />");

		} else if (isset($aDataStreamFeatures["#"]["mechanisms"])&&($aDataStreamFeatures["#"]["mechanisms"][0]["@"]["xmlns"] == "urn:ietf:params:xml:ns:xmpp-sasl")) {

			// Auth
			$this->_log("Authenticating ...");
			$aMechanism = array();
			foreach ($aDataStreamFeatures["#"]["mechanisms"][0]["#"]["mechanism"] as $aRow) {
				$aMechanism[] = $aRow["#"];
			}

			switch (TRUE) {
				case in_array("DIGEST-MD5", $aMechanism):
					$sAuth = "<iq type=\"set\" id=\"".$this->_getUniqueID()."\"><query xmlns=\"jabber:iq:auth\">"
						."<username>".$this->sUser."</username>"
						."<resource>".$this->sResource."</resource>"
						."<digest>".sha1($this->_iStreamID.$this->sPassword)."</digest>"
						."</query></iq>";
					break;
				case in_array("PLAIN", $aMechanism):
					$sAuth = "<auth xmlns=\"urn:ietf:params:xml:ns:xmpp-sasl\" mechanism=\"PLAIN\" >"
						.base64_encode(chr(0).$this->sUser.chr(0).$this->sPassword)
						."</auth>";
				default:
					// other: CRAM-MD5, ANONYMOUS ... etc.
					break;
			}
			$this->_send($sAuth);


		} else if (isset($aDataStreamFeatures["#"]["bind"])&&($aDataStreamFeatures["#"]["bind"][0]["@"]["xmlns"] == "urn:ietf:params:xml:ns:xmpp-bind")) {

			// Bind
			$this->_log("Server Binding Feature");
			$this->_send("<iq type=\"set\" id=\"".$this->_getUniqueID()."\">"
				."<bind xmlns=\"urn:ietf:params:xml:ns:xmpp-bind\">"
				."<resource>".$this->sResource."</resource>"
				."</bind></iq>");
		}
	}

	protected function _parsePresence($aData) {
		if (isset($aData["@"]["type"])) {
			switch ($aData["@"]["type"]) {
				case "subscribe":
					$this->_eventSubscribe($aData["@"]["from"]);
					break;
			}
		}
		if (isset($aData["#"]["status"])) {
			$this->_eventStatus($aData["@"]["from"], $aData["#"]["status"][0]["#"]);
		}
	}

	protected function _parseMessage($aData) {
		if (isset($aData["@"]["type"])&&($aData["@"]["type"] == "chat")) {
			// 收到消息
			$sFrom = $aData["@"]["from"];
			if (isset($aData["#"]["body"][0]["#"])) {
				$sContent = $aData["#"]["body"][0]["#"];
				$this->_eventMessage($sFrom, $sContent);
			}
			// 还有个 jabber:x:event
		} else if (isset($aData["#"]["x"][0]["@"]["xmlns"])&&($aData["#"]["x"][0]["@"]["xmlns"] == "jabber:x:delay")) {
			// offline message
			$sFrom = $aData["@"]["from"];
			$sContent = $aData["#"]["body"][0]["#"];
			$this->_eventMessage($sFrom, $sContent, TRUE);
		}
	}

	protected function _parseIq($aData) {
		if (empty($this->_bAuth)&&isset($aData["@"]["type"])&&($aData["@"]["type"] == "result")) {
			if (isset($aData["@"]["to"])) {
				$this->_eventBind($aData["@"]["to"]);
			}
		}

		if (isset($aData["#"]["bind"])) {
			$this->_eventBind($aData["#"]["bind"][0]["#"]["jid"][0]["#"]);

		} else if (isset($aData["#"]["query"])) {
			$sNameSpace =& $aData["#"]["query"][0]["@"]["xmlns"];
			switch ($sNameSpace) {
				case "jabber:iq:roster":

					$this->_aRoster = array();

					$aDataSub = $aData["#"]["query"][0]["#"]["item"];
					foreach ($aDataSub as $aRoster) {
						$aRoster = $aRoster["@"];
						if ($aRoster["subscription"] == "none") {
							$this->_eventSubscribe($aRoster["jid"]);
						} else {
							$this->_aRoster[$aRoster["jid"]] = 1;
						}
					}
					break;
				case "http://jabber.org/protocol/disco#info":
					break;
			}
		}
	}

	protected function _parseProceed($aData) {
		// TLS Connect
		if ($aData["@"]["xmlns"] == "urn:ietf:params:xml:ns:xmpp-tls") {
			//stream_set_blocking($this->_hStream, 1);
			stream_socket_enable_crypto($this->_hStream, TRUE, STREAM_CRYPTO_METHOD_SSLv23_CLIENT);
			//stream_set_blocking($this->_hStream, 0);
			$this->_connect();
		}
	}

	protected function _parseSuccess($aData) {
		if ($aData['@']['xmlns'] == "urn:ietf:params:xml:ns:xmpp-sasl") {
			$this->_log("Authentication Success");
			$this->_sendStream();
		}
	}

	protected function _parseFailure($aData) {
		// <failure xmlns="urn:ietf:params:xml:ns:xmpp-sasl"><not-authorized/></failure></stream:stream>
		if ($aData['@']['xmlns'] == "urn:ietf:params:xml:ns:xmpp-sasl") {
			$this->_log("Authentication Failure");
			// reAuth or close connect ? but I don't know how to close connect -_-
		}
	}

	/*
	 *  event methods
	 *
	 *  make a extends class methods to replace them, do actions what you want
	 *  I prefer this way than function handling functions
	 */

	protected function _eventConnected() {
		$this->_log("Connected, Stream ID = ".$this->_iStreamID);
	}

	protected function _eventLostConnect() {
		$this->_log("Lost Connect, Wait for 5 sec ...");
	}

	protected function _eventMessage($sFrom, $sContent, $bOffline = FALSE) {
		$this->_log("Message ".($bOffline ? "(Offline)" : "")."from ".$sFrom.": ".$sContent);
		$sReply = $bOffline ? "Offline Message Received." : "Got It.";
		$this->_sendMessage($sFrom, $sReply);
	}

	protected function _eventSubscribe($sJID) {
		$this->_sendPresence("subscribed", $sJID); // appect any subscribe quest
	}

	protected function _eventBind($sJID) {
		$this->_log("Login Over, JID = ".$sJID);
		$this->sJID = $sJID;
		$this->_bAuth = TRUE;
//		$this->_sendServiceDiscovery();
//		$this->_sendIqGet("version");
//		$this->_sendIqGet("browse");
		$this->_sendIqGet("roster");
		$this->_sendSetStatus($this->sStatus);
	}

	protected function _eventStatus($sJID, $sStatus) {
	}

	/*
	 *  send methods, some common send format
	 */

	protected function _sendMessage($sTo, $sContent) {
		$sXML  = "<message type=\"chat\" from=\"".$this->sJID."\" to=\"".$sTo."\">";
		$sXML .= "<body>".$this->_xmlOut($sContent)."</body>";
		$sXML .= "</message>";
		$this->_send($sXML);
	}

	protected function _sendIqGet($sType) {
		$this->_send("<iq type=\"get\" id=\"".$this->_getUniqueID()."\"><query xmlns=\"jabber:iq:".$sType."\"/></iq>");
	}

	protected function _sendServiceDiscovery() {
		$this->_send("<iq type=\"get\" to=\"".$this->sHostTo."\"><query xmlns=\"http://jabber.org/protocol/disco#info\"/></iq>");
	}

	protected function _sendPresence($sType, $sTo) {
		if (!in_array($sType, $this->_aPresenceType)) {
			$this->_log("_sendPresence Method, \$sType Error");
			return FALSE;
		}
		$this->_send("<presence from=\"".$this->sJID."\" to=\"".$sTo."\" type=\"".$sType."\" />");
	}

	protected function _sendSetStatus($sStatus = null, $sShow = "chat") {
		$sXML = "<presence>";
		$sXML .= "<show>".$sShow."</show>";
		if ($sStatus) {
			$sXML .= "<status>".$sStatus."</status>";
		}
		$sXML .= "</presence>";
		$this->_send($sXML);
	}

	protected function _sendStream() {
		$sData = "<stream:stream to=\"".$this->sHostTo."\" "
			."xmlns:stream=\"http://etherx.jabber.org/streams\" "
			."xmlns=\"jabber:client\" version=\"1.0\">";
		$this->_send($sData);
	}

	protected function _isConnected() {
		if (empty($this->_hStream)) {
			return FALSE;
		}
		if (feof($this->_hStream)) {
			return FALSE;
		}
		return TRUE;
	}

	// Connect to server, or reconnect it because TLS
	protected function _connect() {

		if (!$this->_isConnected()) {
			$this->_log("Connecting");
			if ($this->_hStream = @fsockopen($this->sHost, $this->iPort, $iError, $sError, $this->iTimeout)) {
				$this->_log("Connect Success");
				stream_set_blocking($this->_hStream, 0);
				stream_set_timeout($this->_hStream, 3600 * 24);
			} else {
				$this->_log("Connect Failure : ".$iError." ".trim($sError));
				return FALSE;
			}
		} else {
			$this->_log("ReConnecting");
		}

		if (empty($this->sHostTo)) {
			$this->sHostTo = $this->sHost;
		}

		$this->_send("<?xml version=\"1.0\" encoding=\"UTF-8\" ?>");
		$this->_sendStream();
		return TRUE;
	}

	/*
	 *  2 most frequent methods
	 *
	 *  the base way to talk with XMPP server
	 */

	// Send Pocket
	protected function _send($sData) {
		$this->_logPocket($sData, TRUE);
		return fwrite($this->_hStream, $sData."\n");
	}

	// Receive Pocket, and cover XML to Array
	protected function _receive() {
		$sReturn = "";
		for ($i = 0; $i < 100; $i++) {
			$sRead = fread($this->_hStream, 2048);
			if (empty($sRead)) {
				break;
			}
			$sReturn .= $sRead;
		}
		$sReturn = trim($sReturn);

		if (empty($sReturn)) {
			return FALSE;
		} else {
			$this->_logPocket($sReturn, FALSE);
			$aXML = xmlize($sReturn);
			if (!empty($this->bDebug)) {
				file_put_contents("last_xml.txt", print_r($aXML, TRUE));
			}
			return $aXML;
		}
	}

	/*
	 *  2 log methods, when $this->bDebug = FALSE, they will stop work
	 *
	 *  Recommended read log.txt and log_pocket.txt by "tail -f"
	 *  If you are in Windows Platform,
	 *  you can get it from http://unxutils.sf.net
	 */

	// main log
	protected function _log($sMessage) {
		if (empty($this->bDebug)) {
			return FALSE;
		}
		if (empty($this->_hLogWork)) {
			$this->_hLogWork = $this->_logWrite("log.txt");
		}
		fwrite($this->_hLogWork, "\n - ".date("H:i:s")." - ".$sMessage."\n");
	}

	// pocket log,
	protected function _logPocket($sMessage, $bSend = TRUE) {
		if (empty($this->bDebug)) {
			return FALSE;
		}
		$sFileName = "log_pocket.txt";
		if (empty($this->_hLogPocket)) {
			$this->_hLogPocket = $this->_logWrite("log_pocket.txt");
		}
		$sLog = "\n - ".date("Y-m-d H:i:s")." - ".($bSend ? "SEND >>" : "RECV <<")." :\n".$sMessage."\n";
		fwrite($this->_hLogPocket, $sLog);
	}

	protected function _logWrite($sFileName) {
		$sMode = "a";
        if (file_exists($sFileName)) {
                clearstatcache();
                $iFilesize = filesize($sFileName);
                if (($iFilesize < 1)||($iFilesize > 900000000)) { // 900M
                        $sMode = "w";
                }
        }
        return fopen($sFileName, $sMode."b");
	}

	/*
	 *  some utility methods
	 */

	// XML char filter (see also $this->_aXMLChar)
	protected function _xmlOut($sContent) {
		if (empty($this->_aXMLCharOut)) {
			foreach ($this->_aXMLChar as $sChar) {
				$this->_aXMLCharOut[] = "&#".sprintf("%02d", ord($sChar)).";";
			}
		}
		$sContent = str_replace($this->_aXMLChar, $this->_aXMLCharOut, $sContent);
		return $sContent;
	}

	protected function _getUniqueID($sType = null) {
		$this->_iUniqueID++;
		return $this->_iUniqueID;
		// I think there is no need to use complex unique id now, like following line
		return $sType."_".sprintf("%08s", dechex(crc32($sType."\n".microtime(TRUE)."\n".$this->sPassword."\n".$this->sHostTo."\n".$this->sUser."\n".$this->_iUniqueID)));
	}

	protected function _getCleanJID($sJID) {
		$aJID = explode("/", $sJID, 2);
		return $aJID[0];
	}
}
?>
