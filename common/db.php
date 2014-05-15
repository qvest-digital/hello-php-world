<?php

function db_error() {
	global $dbconn;

	if (!isset($dbconn))
		return '(database not set up)';

	return pg_last_error($dbconn);
}

function db_die($reason, $dberr=true) {
	echo 'E: ' . $reason;
	if ($dberr)
		echo ': ' . db_error();
	echo "\n";
	exit(1);
}

function db_connect_if_needed() {
	global $dbconn;

	if (!isset($dbconn))
		db_connect();
}

function db_connect() {
	global $dbconn;

	if (!function_exists('pg_pconnect'))
		db_die('missing PostgreSQL interface for PHP: function pg_pconnect does not exist!', false);

	require_once('/var/lib/hello-php-world/dbconfig.inc');
	if ($dbpass && !$dbserver) {
		/* by default, peer auth for IPC, password auth for localhost */
		$dbserver = 'localhost';
		$dbport = '5432';
	}
	$s = 'dbname=' . $dbname;
	if ($dbuser)
		$s .= ' user=' . $dbuser;
	if ($dbpass)
		$s .= ' password=' . $dbpass;
	if ($dbserver)
		$s .= ' host=' . $dbserver;
	if ($dbport)
		$s .= ' port=' . $dbport;
	//D: error_log('pg_pconnect("' . $s . '")');
	if (!($dbconn = pg_pconnect($s)))
		db_die('could not connect to database');

	// register_shutdown_function for ROLLBACK
}

function db_query_params($sql, $params) {
	global $dbconn;

	db_connect_if_needed();
	if (!($res = @pg_query_params($dbconn, $sql, $params))) {
		error_log('SQL: ' . preg_replace('/\n\s+/', ' ', $sql));
		error_log('SQL> ' . db_error());
	}
	return $res;
}

function db_numrows($h) {
	return @pg_numrows($h);
}

function db_free_result($h) {
	return @pg_freeresult($h);
}

function db_result($h,$row,$field) {
	return @pg_result($h, $row, $field);
}

function db_affected_rows($h) {
	return @pg_cmdtuples($h);
}

function db_fetch_array($h) {
	return @pg_fetch_array($h);
}

function db_insertid($h,$table_name,$table_pkey) {
	$res = db_query_params("SELECT max($table_pkey) AS id FROM $table_name");
	return (db_numrows($res) > 0) ? db_result($res, 0, 'id') : 0;
}
