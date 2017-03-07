<?php
/**
 * Copyright © 2010, 2011, 2012, 2013, 2014, 2015, 2016, 2017
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

require_once('/var/lib/hello-php-world/version.php');

/* replace this with your own custom proper error handling */
function util_debugJ() {
	$argc = func_num_args();
	$argv = func_get_args();
	$skip = 0;

	/* should not happen except extremely early */
	if (!function_exists('minijson_encdbg')) {
		print_r($argv);
		debug_print_backtrace();
		return;
	}

	$loglevel = 'D';
	if ($argc && $argv[0] === 'ERR') {
		$loglevel = 'E';
		/* skip loglevel */
		$argc--;
		array_shift($argv);
	}

	while ($argc && $argv[0] === NULL) {
		/* skip backtrace levels, one for each leading NULL */
		$skip++;
		$argc--;
		array_shift($argv);
	}

	$bt = debug_backtrace();
	$cm = '<no backtrace>';
	if (isset($bt[$skip]) && isset($bt[$skip]['file'])) {
		/* calculate backtrace info: file and line */
		$cm = sprintf('%s[%d]',
		    $bt[$skip]['file'],
		    util_ifsetor($bt[$skip]['line'], -1));

		if (isset($bt[$skip + 1])) {
			$cm .= ': ';
			/* calling method: if set, begin with class */
			$cm .= util_ifsetor($bt[$skip + 1]['class'], '');
			/* calling type; / if not set but we have class */
			$cm .= util_ifsetor($bt[$skip + 1]['type']) ?
			    $bt[$skip + 1]['type'] :
			    (util_ifsetor($bt[$skip + 1]['class']) ? '/' : '');
			/* called function */
			$cm .= util_ifsetor($bt[$skip + 1]['function'],
			    '<unknown>');
			/* func arguments, JSON encoded but with () ipv [] */
			$cm .= preg_replace('/^.(.*).$/', '(\1)',
			    minijson_encdbg(util_ifsetor($bt[$skip + 1]['args'],
			    array()), false));
		}
	}

	if ($argc > 0) {
		/* is first argument 7bit ASCII string, no C0 ctrl chars? */
		if (is_string($argv[0]) && !preg_match('/[^ -~]/', $argv[0])) {
			/* shift it to front */
			$cm .= ': ' . array_shift($argv);
			$argc--;
		}
	}

	if ($argc == 1) {
		/* omit the [] for only one argument */
		$argv = $argv[0];
	}

	if ($argc != 0) {
		/* append any arguments left */
		$cm .= ': ' . minijson_encdbg($argv);
	}
	echo $loglevel . ': ' . trim(str_replace("\n", "\nN: ", $cm)) . "\n";
}

/**
 * return $1 if $1 is set, ${2:-false} otherwise
 *
 * Shortcomings: may create $$val = NULL in the
 * current namespace; see the (rejected – but
 * then, with PHP, you know where you stand…)
 * https://wiki.php.net/rfc/ifsetor#userland_2
 * proposal for details and a (rejected) fix.
 *
 * Do not use this function if $val is “magic”,
 * for example, an overloaded \ArrayAccess.
 */
function util_ifsetor(&$value, $default=false) {
	return (isset($value) ? $value : $default);
}

/* return $1 is $1 is a scalar, ${2:-false} if unset, ${3:-false} otherwise */
/* same shortcomings util_ifsetor() has */
function util_ifscalaror(&$val, $default=false, $ifarray=false) {
	return (isset($val) ? (is_array($val) ? $ifarray : $val) : $default);
}

/* define if not yet defined */
function define_dfl($k, $v) {
	if (!defined($k))
		define($k, $v);
}

function set_dfl($k, $v) {
	global $$k;

	if (!isset($$k))
		$$k = $v;
}

/* HTML stuff */
function html_header($p=array()) {
	//global $html_footer_p;

	//$html_footer_p = $p;
	$lines = array(
		'<' . '?xml version="1.0"?>',
		'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"',
		' "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
		'<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en"><head>',
		' <meta http-equiv="content-type" content="text/html; charset=utf-8" />',
		' <title>' . util_html_encode(util_ifsetor($p['title'], 'untitled page')) . '</title>',
	    );
	foreach (util_ifsetor($p['head'], array()) as $line)
		$lines[] = $line;
	$lines[] = '</head><body>';

	foreach ($lines as $line)
		echo $line . "\n";
}

function html_footer($p=array()) {
	//global $html_footer_p;

	//$p = array_merge($html_footer_p, $p);
	echo "</body></html>\n";
}

/* escape a string into HTML for safe output */
function util_html_encode($s) {
	return htmlspecialchars(''.$s, ENT_QUOTES, 'UTF-8');
}

/* unconvert a string converted with util_html_encode() or htmlspecialchars() */
function util_unconvert_htmlspecialchars($s) {
	return html_entity_decode(''.$s, ENT_QUOTES | ENT_XHTML, 'UTF-8');
}

/* secure a (possibly already HTML encoded) string */
function util_html_secure($s) {
	return util_html_encode(util_unconvert_htmlspecialchars($s));
}

/* convert text to ASCII CR-LF by (\r*\n|\r(?!\n)) */
function util_sanitise_multiline_submission($text) {
	/* convert all CR-LF into LF */
	$text = preg_replace("/\015+\012+/m", "\012", ''.$text);
	/* convert all CR or LF into CR-LF */
	$text = preg_replace("/[\012\015]/m", "\015\012", $text);

	return $text;
}

/* convert text to UTF-8 (from UTF-8 or cp1252 or question marks); nil⇒nil */
function util_fixutf8($s) {
	if ($s === NULL)
		return NULL;
	$s = ''.$s;

	if (!function_exists('mb_internal_encoding') ||
	    !function_exists('mb_convert_encoding')) {
		/* we cannot deal with this without the mb functions */
		return preg_replace('/[^\x01-\x7E]/', '?', $s);
	}
	/* save state */
	$mb_encoding = mb_internal_encoding();
	/* we use Unicode */
	mb_internal_encoding('UTF-8');
	/* check encoding */
	$w = mb_convert_encoding($s, 'UTF-16LE', 'UTF-8');
	$n = mb_convert_encoding($w, 'UTF-8', 'UTF-16LE');
	if ($n === $s) {
		/* correct UTF-8, restore state and return */
		if ($mb_encoding !== false)
			mb_internal_encoding($mb_encoding);
		return ($n);
	}
	/* parse as cp1252 loosely */
	$n = str_split($s);
	$w = '';
	foreach ($n as $v) {
		switch (($c = ord($v[0]))) {
		case 0x80: $wc = 0x20AC; break;
		case 0x81: $wc = 0x003F; break;	/* not in cp1252 */
		case 0x82: $wc = 0x201A; break;
		case 0x83: $wc = 0x0192; break;
		case 0x84: $wc = 0x201E; break;
		case 0x85: $wc = 0x2026; break;
		case 0x86: $wc = 0x2020; break;
		case 0x87: $wc = 0x2021; break;
		case 0x88: $wc = 0x02C6; break;
		case 0x89: $wc = 0x2030; break;
		case 0x8A: $wc = 0x0160; break;
		case 0x8B: $wc = 0x2039; break;
		case 0x8C: $wc = 0x0152; break;
		case 0x8D: $wc = 0x003F; break;	/* not in cp1252 */
		case 0x8E: $wc = 0x017D; break;
		case 0x8F: $wc = 0x003F; break;	/* not in cp1252 */
		case 0x90: $wc = 0x003F; break;	/* not in cp1252 */
		case 0x91: $wc = 0x2018; break;
		case 0x92: $wc = 0x2019; break;
		case 0x93: $wc = 0x201C; break;
		case 0x94: $wc = 0x201D; break;
		case 0x95: $wc = 0x2022; break;
		case 0x96: $wc = 0x2013; break;
		case 0x97: $wc = 0x2014; break;
		case 0x98: $wc = 0x02DC; break;
		case 0x99: $wc = 0x2122; break;
		case 0x9A: $wc = 0x0161; break;
		case 0x9B: $wc = 0x203A; break;
		case 0x9C: $wc = 0x0153; break;
		case 0x9D: $wc = 0x003F; break;	/* not in cp1252 */
		case 0x9E: $wc = 0x017E; break;
		case 0x9F: $wc = 0x0178; break;
		default: $wc = $c; break;
		}
		$w .= chr($wc & 0xFF) . chr($wc >> 8);
	}
	/* convert to UTF-8, then double-check */
	$n = mb_convert_encoding($w, 'UTF-8', 'UTF-16LE');
	$x = mb_convert_encoding($n, 'UTF-16LE', 'UTF-8');
	/* restore caller state saved */
	if ($mb_encoding !== false)
		mb_internal_encoding($mb_encoding);
	if ($w !== $x) {
		/* something went wrong in Unicode land */
		return preg_replace('/[^\x01-\x7E]/', '?', $s);
	}
	/* return UTF-8 result string */
	return $n;
}

/* get a backtrace as string */
function debug_string_backtrace() {
	ob_start();
	debug_print_backtrace();
	$trace = ob_get_contents();
	ob_end_clean();

	/* remove first item (this function, i.e. redundant) from backtrace */
	$trace = preg_replace('/^#0\s+' . __FUNCTION__ . "[^\n]*\n/", '',
	    $trace, 1);

	/* renumber backtrace items */
	$trace = preg_replace_callback('/^#(\d+)/m', function ($match) {
		return sprintf('#%d', $match[1] - 1);
	    }, $trace);

	return $trace;
}

/* return integral value (ℕ₀) of passed string if it matches, or false */
function util_nat0(&$s) {
	if (!isset($s)) {
		/* unset variable */
		return false;
	}
	if (is_array($s)) {
		if (count($s) == 1) {
			/* one-element array */
			return util_nat0($s[0]);
		}
		/* not one element, or element not at [0] */
		return false;
	}
	if (!is_numeric($s)) {
		/* not numeric */
		return false;
	}
	$num = (int)$s;
	if ($num >= 0) {
		/* number element of ℕ₀ */
		$text = (string)$num;
		if ($text == $s) {
			/* number matches its textual representation */
			return ($num);
		}
		/* doesn’t match, like 0123 or 1.2 or " 1" */
	}
	/* or negative */
	return false;
}

/* JSON stuff which lives separate for hysterical raisins */
require_once('minijson.php');

/* used by structured debugging, see above */
function minijson_encdbg($x, $ri='') {
	return (minijson_encode_internal($x, $ri, 32,
	    defined('JSONDEBUG_TRUNCATE_SIZE') ?
	    constant('JSONDEBUG_TRUNCATE_SIZE') : 0, true));
}
