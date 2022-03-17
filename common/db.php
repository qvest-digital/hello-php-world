<?php
/**
 * Copyright © 2014, 2015, 2017, 2022
 * 	mirabilos <t.glaser@tarent.de>
 *
 * Provided that these terms and disclaimer and all copyright notices
 * are retained or reproduced in an accompanying document, permission
 * is granted to deal in this work without restriction, including un‐
 * limited rights to use, publicly perform, distribute, sell, modify,
 * merge, give away, or sublicence.
 *
 * This work is provided “AS IS” and WITHOUT WARRANTY of any kind, to
 * the utmost extent permitted by applicable law, neither express nor
 * implied; without malicious intent or gross negligence. In no event
 * may a licensor, author or contributor be held liable for indirect,
 * direct, other damage, loss, or other issues arising in any way out
 * of dealing in the work, even if advised of the possibility of such
 * damage or existence of a defect, except proven that it results out
 * of said person’s immediate fault when using the work as intended.
 */

/* (just in case) */
require_once(dirname(__FILE__) . '/util.php');

function db_error() {
	global $dbconn;

	if (!isset($dbconn) || !$dbconn)
		return '(database not set up)';

	return pg_last_error($dbconn);
}

function db_die($reason, $dberr=true) {
	if ($dberr)
		util_debugJ('ERR', true, NULL, $reason, db_error());
	else
		util_debugJ('ERR', true, NULL, $reason);
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

	include('/var/lib/' . HPW_PACKAGE . '/dbconfig.inc');
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
	// connect_timeout=n (default 0=indefinitely; <2 recommended)
	// client_encoding=…
	// fallback_application_name=hello-php-world
	if (!($dbconn = pg_pconnect($s)))
		db_die('could not connect to database');
}

function db_query_params($sql, $params) {
	global $dbconn;

	db_connect_if_needed();
	if (!($res = @pg_query_params($dbconn, $sql, $params))) {
		util_debugJ('ERR', true, NULL, array(
			'database error' => db_error(),
			'failed SQL' => preg_replace('/\n\s+/', ' ', $sql),
		    ));
	}
	return $res;
}

function db_numrows($h) {
	return @pg_num_rows($h);
}

function db_free_result($h) {
	return @pg_free_result($h);
}

function db_result($h, $row, $field) {
	return @pg_fetch_result($h, $row, $field);
}

function db_affected_rows($h) {
	return @pg_affected_rows($h);
}

function db_fetch_array($h) {
	return @pg_fetch_array($h);
}

/* deprecated, use db_insert_one() or db_insert_max() instead */
function db_insertid($table_name, $table_pkey) {
	/* this can potentially be wrong */
	$res = db_query_params('SELECT currval(pg_get_serial_sequence($1, $2)) AS id',
	    array($table_name, $table_pkey));
	return ($res && db_numrows($res) > 0) ? db_result($res, 0, 'id') : false;
}

function db_insert_one($pk, $sql, $params) {
	$res = db_query_params($sql . ' RETURNING ' . $pk, $params);
	return ($res && db_numrows($res) == 1) ? db_result($res, 0, $pk) : false;
}

function db_insert_max($pk, $sql, $params) {
	$res = db_query_params('WITH insrt_from_php AS (' .
	    $sql . ' RETURNING ' . $pk . ') SELECT MAX(' .
	    $pk . ') AS id FROM insrt_from_php', $params);
	return ($res && db_numrows($res) > 0) ? db_result($res, 0, 'id') : false;
}
