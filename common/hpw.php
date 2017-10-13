<?php

/* mostly a demo for the classloader */

/*
 * you could require_once() files here that are necessary for the class
 * but not autoloaded
 */

/* class name does not need to equal filename (not Java™ after all…) */
class HpwWeb {

private $parm;

public function __construct($p=array()) {
	$this->parm = $p;
}

public function setTitle($s) {
	$this->parm['title'] = $s;
}

public function addHeadLine($s) {
	$p = util_ifsetor($this->parm['head'], array());
	$p[] = $s;
	$this->parm['head'] = $p;
}

public function showHeader($p=array()) {
	$this->parm = array_merge($this->parm, $p);

	$lines = array(
		'<' . '?xml version="1.0"?>',
		'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"',
		' "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
		'<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en"><head>',
		' <meta http-equiv="content-type" content="text/html; charset=utf-8" />',
		' <title>' . util_html_encode(util_ifsetor($this->parm['title'], 'untitled page')) . '</title>',
	    );
	foreach (util_ifsetor($this->parm['head'], array()) as $line)
		$lines[] = $line;
	$lines[] = '</head><body>';

	/*
	 * somewhat saner would be to have this function be called
	 * getHeader and have it return implode("\n", $lines)
	 */
	foreach ($lines as $line)
		echo $line . "\n";
}

public function showFooter($p=array()) {
	$this->parm = array_merge($this->parm, $p);

	echo "</body></html>\n";
}

}
