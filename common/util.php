<?php

require_once('/var/lib/hello-php-world/version.php');

function util_ifsetor(&$value, $default=false) {
	return (isset($value) ? $value : $default);
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

function util_html_encode($s) {
	return htmlspecialchars($s, ENT_QUOTES, "UTF-8");
}

function util_unconvert_htmlspecialchars($s) {
	return html_entity_decode($s, ENT_QUOTES | ENT_XHTML, "UTF-8");
}

function util_html_secure($s) {
	return util_html_encode(util_unconvert_htmlspecialchars($s));
}
