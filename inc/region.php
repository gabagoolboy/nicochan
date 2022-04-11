<?php

class Regionblock {

	static public function userAdd($ipaddr) {

	$hash = get_ip_hash($ipaddr);
	$query = prepare("INSERT INTO ``whitelist_region`` VALUES(NULL, :ip, :ip_hash)");
	$query->bindValue(":ip", $ipaddr, PDO::PARAM_STR);
	$query->bindValue(":ip_hash", $hash, PDO::PARAM_STR);
	$query->execute() or error(db_error($query));

	}


	static public function validateIP($ipaddr) {

	$query = prepare("SELECT `ip` FROM ``whitelist_region`` WHERE `ip` = :ip");
	$query->bindValue(":ip", $ipaddr, PDO::PARAM_STR);
	$query->execute() or error(db_error($query));

	if($r = $query->fetchColumn(0)){
		return true;
	}

	return false;
}


	static public function revokeWhitelist($ipaddr) {

	$query = prepare("DELETE FROM ``whitelist_region`` WHERE `ip` = :ip");
	$query->bindValue(":ip", $ipaddr, PDO::PARAM_STR);
	$query->execute() or error(db_error($query));

	}

}
