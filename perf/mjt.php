<?php
/**
 * Copyright © 2020
 *	mirabilos <m@mirbsd.org>
 * Copyright © 2017
 *	mirabilos <t.glaser@tarent.de>
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
error_reporting(-1);
require_once('mjtinc.php');

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
showmemusage('loading', $memload, $membase, msnice($tm2load, $tm1load));
showmemusage('decoding', $memdec, $memfil, msnice($tm2dec, $tm1dec));
showmemusage('encoding', $memenc, $memchk, msnice($tm2enc, $tm1enc));
showmemusage('total', getmemusage(), NULL, msnice(hrtime(), $tm1load));

printf("hash %s %s\n", $hashval, $hashok ? 'matches' : 'FAILURE');

$h = popen('${PHP:-php} mjtenc.php' . (count($argv) > 1 ? (' ' . $argv[1]) : ''), 'w');
fwrite($h, serialize($out));
pclose($h);
