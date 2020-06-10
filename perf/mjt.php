<?php
/**
 * Copyright © 2020
 *	mirabilos <m@mirbsd.org>
 * Copyright © 2017
 *	mirabilos <t.glaser@tarent.de>
 * Ⓕ The MirOS Licence (MirBSD)
 */
error_reporting(-1);

/* timers for performance testing */
if (function_exists('hrtime')) {
	/* PHP ≥ 7.3 */
	function msdiff($second, $first) {
		$d = ($second[0] - $first[0]) * 1000;
		$m1 = $first[1] / 1000000.0;
		$m2 = $second[1] / 1000000.0;
		$d += $m2 - $m1;
		return $d;
	}
} else {
	function hrtime() {
		return microtime(true);
	}
	function msdiff($second, $first) {
		return ($second - $first) * 1000.0;
	}
}
function getmemusage() {
	return array(
		memory_get_usage(false) / 1024.0,
		memory_get_usage(true) / 1024.0,
		memory_get_peak_usage(false) / 1024.0,
		memory_get_peak_usage(true) / 1024.0,
	    );
}
function showmemusage($label, $data, $delta=NULL, $postfix='') {
	if (is_null($delta))
		$delta = array(0, 0, 0, 0);
	printf("%8s: %10.2FK/%10.2FK, peak %10.2FK/%10.2FK%s\n",
	    $label, $data[0] - $delta[0], $data[1] - $delta[1],
	    $data[2] - $delta[2], $data[3] - $delta[3], $postfix);
}

$membase = getmemusage();
$tm1load = hrtime();
require('../common/minijson' . (count($argv) > 1 ? $argv[1] : '') . '.php');
$tm2load = hrtime();
$memload = getmemusage();

$in = file_get_contents('php://stdin');

$memfil = getmemusage();
$tm1dec = hrtime();
$status = minijson_decode($in, $out);
$tm2dec = hrtime();
$memdec = getmemusage();

if (!$status) {
	print_r($out);
	echo "\nE: failed\n";
	exit(1);
}

$memchk = getmemusage();
$tm1enc = hrtime();
$enc = minijson_encode($out);
$tm2enc = hrtime();
$memenc = getmemusage();

$hashval = md5($enc);
$hashok = $hashval === '48d8a06a5d811e1d9fd1729e2b1dc9e9';

showmemusage('baseline', $membase);
showmemusage('loading', $memload, $membase,
    sprintf('%14.6f ms', msdiff($tm2load, $tm1load)));
showmemusage('decoding', $memdec, $memfil,
    sprintf('%14.6f ms', msdiff($tm2dec, $tm1dec)));
showmemusage('encoding', $memenc, $memchk,
    sprintf('%14.6f ms', msdiff($tm2enc, $tm1enc)));
showmemusage('total', getmemusage(), NULL,
    sprintf('%14.6f ms', msdiff(hrtime(), $tm1load)));

printf("hash %s %s\n", $hashval, $hashok ? 'matches' : 'FAILURE');
