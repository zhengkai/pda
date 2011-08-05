<?PHP
require_once("../../xmlize.inc.php");

if (defined("ZK_SERVER_TAG")) {
	class ZabberDB extends MySQLi {
		public function __construct() {
			global $aDB;
			parent::__construct($aDB["host"], $aDB["user"], $aDB["password"], $aDB["dbname"], $aDB["port"], $aDB["socket"]);
			$this->set_charset("utf8");
		}
	}
} else {
	class ZabberDB extends MySQLi {
		public function __construct() {
			// fill your mysql setting here
			parent::__construct("localhost", "user", "password", "dbname", 3306, "/var/run/mysqld/mysqld.sock");
			$this->set_charset("utf8");
		}
	}
}

$oDB = new ZabberDB();

// $m = file_get_contents("public_timeline.rss");
while (1) {
	$s = "http://twitter.com/statuses/public_timeline.rss?".hash("crc32", microtime(TRUE)).time();
	echo "\n".$s."\n";
	$m = file_get_contents($s);
	$m = xmlize($m);
	$m = $m["rss"][0]["#"]["channel"][0]["#"]["item"];
	// file_put_contents("out.txt", print_r($m, TRUE));
	foreach ($m as $aRow) {
		$aRow = $aRow["#"];
		$sContent = html_entity_decode(substr(strstr($aRow["title"][0]["#"], ": "), 2));
		$sTime = date("Y-m-d H:i:s", strtotime($aRow["pubDate"][0]["#"]));
		$iID = array_pop(explode("/", $aRow["guid"][0]["#"]));
		$iType = mt_rand(0, 4);
		$iLength = mb_strlen($sContent);

		echo $iID." ";
//		echo $sContent." ";

		if ($iLength > 50) {
			$sExt     = mb_substr($sContent, 50);
			$sContent = mb_substr($sContent, 0, 50);
		}

		$sQuery = "INSERT INTO msg SET "
			."id = ".$iID.", "
			."content = \"".addslashes($sContent)."\", "
			."length = ".$iLength.", "
			."itype = ".$iType.", "
			."datetime_c = \"".$sTime."\"";

//			echo "\n\n".$sQuery."\n\n";

		$oDB->query($sQuery);

		if (!empty($sExt)) {
			$sQuery = "INSERT INTO msg_content SET "
				."id = ".$iID.", "
				."content_ext = \"".addslashes($sExt)."\"";

			$oDB->query($sQuery);
			$sExt = "";
		}
	}
	sleep(30);
}
?>