<?PHP
/*
 *  command : \help
 */

$sHelp =
	 "Hey, I'm a Robot power by Zabber, a PHP Class\n"
	."You can type some message to me, I will record it.\n\n"
	."except these command:\n"
	."　　\\me : list messages of yourself\n"
	."　　\\ls : list messages of all\n"
	."　　#message : # header will hidden message for others, \n"
	."　　　　they can't see it by \\ls\n"
	."　　!uptime : uptime of me";

$this->_sendMessage($sFrom, $sHelp);
?>