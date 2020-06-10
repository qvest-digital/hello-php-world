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

function msnice($second, $first) {
	return sprintf('%14.6f ms', msdiff($second, $first));
}
