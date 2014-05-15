<?php

require_once('util.php');
require_once('db.php');

html_header(array('title' => 'Hello PHP World'));

echo '<table border="1">';
echo '<tr><th>ID</th><th>Blabla</th></tr>' . "\n";
$res = db_query_params('SELECT * FROM content', array());
while (($row = db_fetch_array($res))) {
	echo '<tr><th>' . $row['id'] . '</th><td>' .
	    util_html_encode($row['blabla']) . '</td></tr>' . "\n";
}
echo '</table>' . "\n";

$res = db_query_params('SELECT version FROM z_schema_version', array());
printf('<p>DB-Schema Version %d</p>', db_result($res, 0, 'version')) . "\n";

echo '<hr /><p><a href="pi.php">PHPinfo();</a></p>' . "\n";

html_footer();
