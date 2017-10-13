<?php

/* this must come first (and adds minijson implicitly) */
require_once('util.php');
/* now this where necessary */
require_once('db.php');

$logId = db_insert_one('id',
    'INSERT INTO log (ts, ip) VALUES (now(), $1)',
    array($_SERVER["REMOTE_ADDR"]));

$html = new HpwWeb();
$html->setTitle('Hello PHP World v' . HPW_VERSION);
$html->showHeader();

$res = db_query_params('SELECT version FROM z_schema_version', array());
$schemavsn = false;
if ($res) {
	if (db_numrows($res) !== 1)
		util_debugJ(true, 'Could not retrieve DB schema version: ' .
		    db_numrows($res) . ' entries found');
	else
		$schemavsn = db_result($res, 0, 'version');
}
if ($schemavsn === false) {
	echo '<p>Could not access the database.</p>' . "\n";
} else {
	echo '<table border="1">';
	echo '<tr><th>ID</th><th>Blabla</th></tr>' . "\n";
	$res = db_query_params('SELECT * FROM content', array());
	while (($row = db_fetch_array($res))) {
		echo '<tr><th>' . $row['id'] . '</th><td>' .
		    util_html_encode($row['blabla']) . '</td></tr>' . "\n";
	}
	echo '</table>' . "\n";

	printf("<p>DB-Schema Version %d</p>\n", $schemavsn);

	printf("<p>I logged your visit as ID #%d</p>\n", $logId);
	$res = db_query_params('SELECT * FROM log WHERE id=$1', array($logId));
	if (($row = db_fetch_array($res))) {
		printf("<p>#%d - %s - %s</p>\n", $row['id'], $row['ts'],
		    util_html_encode($row['ip']));
	} else {
		echo '<p>Database error trying to read the log.</p>' . "\n";
	}
}

echo '<hr /><p><a href="pi.php">PHPinfo();</a></p>' . "\n";

$html->showFooter();
