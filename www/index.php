<?php

require_once('util.php');

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

printf("<p>DB-Schema Version %d</p>\n", HpwDb::GetSchemaVersion());

echo '<hr /><p><a href="pi.php">PHPinfo();</a></p>' . "\n";

html_footer();
