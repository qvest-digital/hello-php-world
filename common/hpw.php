<?php

require_once('db.php');

class HpwDb {

private $res;

static public function GetSchemaVersion() {
	$res = db_query_params('SELECT version FROM z_schema_version', array());
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
	return db_fetch_array($this->res);
}

/* class HpwDb */
}
