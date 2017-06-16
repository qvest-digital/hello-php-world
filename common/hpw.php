<?php

/**
 * Note: this is only an example demonstrating the class autoloader.
 * In a real-world application you would just use the db_query_params()
 * and other functions directly from within your own code.
 */

require_once('db.php');

class HpwDb {

private $res;

static public function GetSchemaVersion() {
	$res = db_query_params('SELECT version FROM z_schema_version', array());
	if (!$res)
		return false;
	if (db_numrows($res) !== 1) {
		util_debugJ(true, 'Could not retrieve DB schema version: ' .
		    db_numrows($res) . ' entries found');
		return false;
	}
	return db_result($res, 0, 'version');
}

public function __construct() {
	$this->res = NULL;
	$this->logId = db_insert_one('id',
	    'INSERT INTO log (ts, ip) VALUES (now(), $1)',
	    array($_SERVER["REMOTE_ADDR"]));
}

public function getLogId() {
	return $this->logId;
}

public function Query($sql, $params=array()) {
	$this->res = db_query_params($sql, $params);
}

public function NextRow() {
	return ($this->res ? db_fetch_array($this->res) : false);
}

/* class HpwDb */
}
