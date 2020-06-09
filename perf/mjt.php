<?php
error_reporting(-1);
require('../common/minijson' . (count($argv) > 1 ? $argv[1] : '') . '.php');
$status = minijson_decode(file_get_contents('php://stdin'), $out);
if (!$status) {
	print_r($out);
	echo "\nE: failed\n";
	exit(1);
}
//print_r(array('s'=>$status,'z'=>$out));

echo number_format((memory_get_usage(false)/1024.0), 2, '.', "'") . "K/" .
  number_format((memory_get_usage(true)/1024.0), 2, '.', "'") . "K, peak " .
  number_format((memory_get_peak_usage(false)/1024.0), 2, '.', "'") . "K/" .
  number_format((memory_get_peak_usage(true)/1024.0), 2, '.', "'") . "K\n";
