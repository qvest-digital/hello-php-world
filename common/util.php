<?php
/**
 * Copyright © 2014, 2015, 2016
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
	return htmlspecialchars($s, ENT_QUOTES, "UTF-8");
}

/* unconvert a string converted with util_html_encode() or htmlspecialchars() */
function util_unconvert_htmlspecialchars($s) {
	return html_entity_decode($s, ENT_QUOTES | ENT_XHTML, "UTF-8");
}

/* secure a (possibly already HTML encoded) string */
function util_html_secure($s) {
	return util_html_encode(util_unconvert_htmlspecialchars($s));
}
