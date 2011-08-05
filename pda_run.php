#!/usr/bin/php
<?PHP
chdir(__DIR__);

require_once(__DIR__."/zabber.class.php");
require_once(__DIR__."/zabberex.class.php");
require_once(__DIR__."/twitter.oauth.php");

if (defined("ZK_SERVER_TAG")) {
	define("TWITTER_SOCKS_PROXY", ZK_SERVER_TAG == "aeon" ? "127.0.0.1:7780" : "192.168.0.151:7780");
}

class ZabberDB extends MySQLi {
	public function __construct() {
		// fill your mysql setting here
		parent::__construct("localhost", "soulogic", "qwHoSB1zozo2mhYSDB3OH", "soulogic_pda", 3306, "/var/run/mysqld/mysqld.sock");
		$this->set_charset("utf8");
	}
}

/*
 * Connect to Google Talk Server
 */

$oBot = new ZabberEx();
$oBot->sHost	 = "talk.google.com";
$oBot->sHostTo   = "gmail.com";

$oBot->sUser	 = "your name @gmail.com";
$oBot->sPassword = "fill your password here";

$oBot->sStatus   = "Recorder";
$oBot->run();
