<?php

/* this must come first */
require_once('util.php');
/* include db.php here or, indirectly, via autoloader (see below) */

$db = new HpwDb();

html_header(array('title' => 'Hello PHP World v' . HPW_VERSION));

echo '<table border="1">';
echo '<tr><th>ID</th><th>Blabla</th></tr>' . "\n";
$db->Query('SELECT * FROM content');
while (($row = $db->NextRow())) {
	echo '<tr><th>' . $row['id'] . '</th><td>' .
	    util_html_encode($row['blabla']) . '</td></tr>' . "\n";
}
echo '</table>' . "\n";

if (($schemavsn = HpwDb::GetSchemaVersion()) === NULL) {
	echo '<p>Could not access the database.</p>' . "\n";
} else {
	printf("<p>DB-Schema Version %d</p>\n", HpwDb::GetSchemaVersion());

	printf("<p>I logged your visit as ID #%d</p>\n", $db->getLogId());
	$db->Query('SELECT * FROM log WHERE id=$1', array($db->getLogId()));
	$row = $db->NextRow();
	printf("<p>#%d - %s - %s</p>\n", $row['id'], $row['ts'],
	    util_html_encode($row['ip']));
}

echo '<hr /><p><a href="pi.php">PHPinfo();</a></p>' . "\n";

html_footer();
